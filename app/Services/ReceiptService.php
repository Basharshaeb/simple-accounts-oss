<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Receipt;
use Exception;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    /**
     * Create a new receipt voucher (Draft or Auto-Posted).
     */
    public function create(array $data, bool $autoPost = false): Receipt
    {
        return DB::transaction(function () use ($data, $autoPost) {
            $companyId = $data['company_id'];
            $receiptDate = $data['receipt_date'];

            $rawLines = $data['lines'] ?? [];
            if (empty($rawLines) && ! empty($data['account_id'])) {
                $rawLines = [
                    [
                        'account_id' => $data['account_id'],
                        'amount' => (float) ($data['amount'] ?? 0),
                        'description' => $data['description'] ?? null,
                    ],
                ];
            }

            if (empty($rawLines)) {
                throw new Exception('يجب إضافة سطر حساب واحد على الأقل في سند القبض.');
            }

            $totalAmount = 0.0;
            $processedLines = [];
            foreach ($rawLines as $l) {
                $lAmt = (float) ($l['amount'] ?? 0);
                if ($lAmt <= 0) {
                    throw new Exception('مبلغ كل سطر في سند القبض يجب أن يكون أكبر من صفر.');
                }
                $totalAmount += $lAmt;
                $processedLines[] = [
                    'account_id' => $l['account_id'],
                    'amount' => $lAmt,
                    'description' => $l['description'] ?? null,
                ];
            }

            $exchangeRate = (float) ($data['exchange_rate'] ?? 1.0);
            $baseAmount = round($totalAmount * $exchangeRate, 4);

            $year = (int) date('Y', strtotime($receiptDate));
            $receiptNumber = DocumentNumberingService::generate($companyId, 'RV', $year);

            $primaryAccountId = $processedLines[0]['account_id'];

            $receipt = Receipt::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'receipt_number' => $receiptNumber,
                'receipt_date' => $receiptDate,
                'account_id' => $primaryAccountId,
                'cash_or_bank_account_id' => $data['cash_or_bank_account_id'],
                'amount' => $totalAmount,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $exchangeRate,
                'base_amount' => $baseAmount,
                'payment_method' => $data['payment_method'] ?? 'CASH',
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? 'سند قبض رقم '.$receiptNumber,
                'status' => 'DRAFT',
                'created_by' => $data['created_by'],
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $data['created_by'],
                'action' => 'CREATE_RECEIPT_VOUCHER',
                'auditable_type' => Receipt::class,
                'auditable_id' => $receipt->id,
                'new_values' => array_merge($receipt->toArray(), ['lines' => $processedLines]),
            ]);

            if ($autoPost) {
                $this->post($receipt, $data['created_by'], $processedLines);
            }

            return $receipt->fresh();
        });
    }

    /**
     * Post a receipt voucher and auto-generate linked journal entry.
     */
    public function post(Receipt $receipt, int $userId, array $lines = []): Receipt
    {
        return DB::transaction(function () use ($receipt, $userId, $lines) {
            if ($receipt->status === 'POSTED') {
                throw new Exception('سند القبض معتمد بالفعل.');
            }

            $journalService = app(JournalEntryService::class);

            $jeLines = [];
            // 1. Debit Cash/Bank for full amount
            $jeLines[] = [
                'account_id' => $receipt->cash_or_bank_account_id,
                'description' => 'قبض نقدية/بنك بموجب سند '.$receipt->receipt_number,
                'debit' => $receipt->amount,
                'credit' => 0,
                'exchange_rate' => $receipt->exchange_rate,
            ];

            // 2. Credit lines (Multi-party)
            if (! empty($lines)) {
                foreach ($lines as $l) {
                    $jeLines[] = [
                        'account_id' => $l['account_id'],
                        'description' => $l['description'] ?? ('استلام من حساب بموجب سند '.$receipt->receipt_number),
                        'debit' => 0,
                        'credit' => (float) $l['amount'],
                        'exchange_rate' => $receipt->exchange_rate,
                    ];
                }
            } else {
                $jeLines[] = [
                    'account_id' => $receipt->account_id,
                    'description' => 'استلام من حساب بموجب سند '.$receipt->receipt_number,
                    'debit' => 0,
                    'credit' => $receipt->amount,
                    'exchange_rate' => $receipt->exchange_rate,
                ];
            }

            $journalEntry = $journalService->create([
                'company_id' => $receipt->company_id,
                'branch_id' => $receipt->branch_id,
                'entry_date' => $receipt->receipt_date->toDateString(),
                'description' => $receipt->description ?? 'سند قبض رقم '.$receipt->receipt_number,
                'reference' => $receipt->receipt_number,
                'currency_id' => $receipt->currency_id,
                'exchange_rate' => $receipt->exchange_rate,
                'source_type' => 'RECEIPT',
                'source_id' => $receipt->id,
                'created_by' => $userId,
                'lines' => $jeLines,
            ], autoPost: true);

            $receipt->update([
                'status' => 'POSTED',
                'journal_entry_id' => $journalEntry->id,
            ]);

            AuditLog::create([
                'company_id' => $receipt->company_id,
                'user_id' => $userId,
                'action' => 'POST_RECEIPT_VOUCHER',
                'auditable_type' => Receipt::class,
                'auditable_id' => $receipt->id,
                'new_values' => ['status' => 'POSTED', 'journal_entry_id' => $journalEntry->id],
            ]);

            return $receipt;
        });
    }
}
