<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * Create a new journal entry.
     */
    public function create(array $data, bool $autoPost = false): JournalEntry
    {
        return DB::transaction(function () use ($data, $autoPost) {
            $companyId = $data['company_id'];
            $entryDate = $data['entry_date'];
            $defaultCurrencyId = $data['currency_id'] ?? null;
            $defaultExchangeRate = (float) ($data['exchange_rate'] ?? 1.0);

            // Find Open Fiscal Period
            $fiscalPeriod = FiscalPeriod::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('start_date', '<=', $entryDate)
                ->where('end_date', '>=', $entryDate)
                ->first();

            if (! $fiscalPeriod) {
                throw new Exception('لا توجد فترة مالية محددة لهذا التاريخ: '.$entryDate);
            }

            if ($fiscalPeriod->status === 'CLOSED') {
                throw new Exception('الفترة المالية لهذا التاريخ مغلقة، لا يمكن إضافة قيود.');
            }

            $linesData = $data['lines'] ?? [];
            if (count($linesData) < 2) {
                throw new Exception('القيد اليومي يجب أن يحتوي على سطرين على الأقل.');
            }

            $totalDebitBase = 0.0;
            $totalCreditBase = 0.0;

            $processedLines = [];

            foreach ($linesData as $index => $line) {
                $accountId = $line['account_id'];
                $account = Account::withoutGlobalScopes()->with('currencies')->find($accountId);

                if (! $account || $account->company_id !== $companyId) {
                    throw new Exception("الحساب رقم (#{$accountId}) غير موجود أو لا ينتمي لهذه الشركة.");
                }

                if ($account->is_group) {
                    throw new Exception("الحساب (#{$account->code} - {$account->name}) حساب تجميعي لا يمكن التسجيل عليه.");
                }

                if (! $account->is_postable || ! $account->is_active) {
                    throw new Exception("الحساب (#{$account->code} - {$account->name}) غير نشط أو غير مسموح بالتسجيل عليه.");
                }

                $lineCurrencyId = $line['currency_id'] ?? $defaultCurrencyId;
                if (! $lineCurrencyId) {
                    $company = Company::find($companyId);
                    $lineCurrencyId = $company ? $company->base_currency_id : null;
                }

                // Check allowed currencies for this account if explicitly linked
                if ($account->currencies->count() > 0 && $lineCurrencyId) {
                    $allowedCurrencyIds = $account->currencies->pluck('id')->toArray();
                    if (! in_array($lineCurrencyId, $allowedCurrencyIds)) {
                        throw new Exception("العملة المحددة للسطر غير مرتبطة بالحساب (#{$account->code} - {$account->name}). العملات المسموحة فقط: ".implode(', ', $account->currencies->pluck('code')->toArray()));
                    }
                }

                $lineRate = (float) ($line['exchange_rate'] ?? $defaultExchangeRate);
                $debit = (float) ($line['debit'] ?? 0.0);
                $credit = (float) ($line['credit'] ?? 0.0);

                if ($debit < 0 || $credit < 0) {
                    throw new Exception('المبالغ المحاسبية يجب أن تكون قيمًا موجبة.');
                }

                if ($debit == 0 && $credit == 0) {
                    throw new Exception('يجب تحديد مبلغ مدين أو دائن لسطر القيد.');
                }

                if ($debit > 0 && $credit > 0) {
                    throw new Exception('لا يمكن تسجيل مبلغ مدين ودائن معًا في نفس السطر.');
                }

                $baseDebit = round($debit * $lineRate, 4);
                $baseCredit = round($credit * $lineRate, 4);

                $totalDebitBase += $baseDebit;
                $totalCreditBase += $baseCredit;

                $processedLines[] = [
                    'account_id' => $account->id,
                    'currency_id' => $lineCurrencyId,
                    'description' => $line['description'] ?? $data['description'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'foreign_debit' => $debit,
                    'foreign_credit' => $credit,
                    'exchange_rate' => $lineRate,
                    'base_debit' => $baseDebit,
                    'base_credit' => $baseCredit,
                ];
            }

            // Double Entry Check
            if (abs($totalDebitBase - $totalCreditBase) > 0.0001) {
                throw new Exception(sprintf('القيد المحاسبي غير متوازن! إجمالي المدين (%s) لا يساوي إجمالي الدائن (%s).', number_format($totalDebitBase, 2), number_format($totalCreditBase, 2)));
            }

            $year = (int) date('Y', strtotime($entryDate));
            $entryNumber = DocumentNumberingService::generate($companyId, 'JE', $year);

            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'fiscal_period_id' => $fiscalPeriod->id,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
                'currency_id' => $defaultCurrencyId,
                'exchange_rate' => $defaultExchangeRate,
                'total_debit' => $totalDebitBase,
                'total_credit' => $totalCreditBase,
                'status' => $autoPost ? 'POSTED' : 'DRAFT',
                'source_type' => $data['source_type'] ?? 'MANUAL',
                'source_id' => $data['source_id'] ?? null,
                'posted_by' => $autoPost ? $data['created_by'] : null,
                'posted_at' => $autoPost ? now() : null,
                'created_by' => $data['created_by'],
            ]);

            foreach ($processedLines as $pLine) {
                $entry->lines()->create($pLine);
            }

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $data['created_by'],
                'action' => $autoPost ? 'POST_JOURNAL' : 'CREATE_JOURNAL_DRAFT',
                'auditable_type' => JournalEntry::class,
                'auditable_id' => $entry->id,
                'new_values' => $entry->toArray(),
            ]);

            return $entry;
        });
    }
}
