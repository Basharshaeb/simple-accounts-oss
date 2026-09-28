<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Create a new payment voucher (Draft or Auto-Posted).
     */
    public function create(array $data, bool $autoPost = false): Payment
    {
        return DB::transaction(function () use ($data, $autoPost) {
            $companyId = $data['company_id'];
            $paymentDate = $data['payment_date'];

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
                throw new Exception('يجب إضافة سطر حساب واحد على الأقل في سند الصرف.');
            }

            $totalAmount = 0.0;
            $processedLines = [];
            foreach ($rawLines as $l) {
                $lAmt = (float) ($l['amount'] ?? 0);
                if ($lAmt <= 0) {
                    throw new Exception('مبلغ كل سطر في سند الصرف يجب أن يكون أكبر من صفر.');
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

            $year = (int) date('Y', strtotime($paymentDate));
            $paymentNumber = DocumentNumberingService::generate($companyId, 'PV', $year);

            $primaryAccountId = $processedLines[0]['account_id'];

            $payment = Payment::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'payment_number' => $paymentNumber,
                'payment_date' => $paymentDate,
                'account_id' => $primaryAccountId,
                'cash_or_bank_account_id' => $data['cash_or_bank_account_id'],
                'amount' => $totalAmount,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $exchangeRate,
                'base_amount' => $baseAmount,
                'payment_method' => $data['payment_method'] ?? 'CASH',
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? 'سند صرف رقم '.$paymentNumber,
                'status' => 'DRAFT',
                'created_by' => $data['created_by'],
            ]);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $data['created_by'],
                'action' => 'CREATE_PAYMENT_VOUCHER',
                'auditable_type' => Payment::class,
                'auditable_id' => $payment->id,
                'new_values' => array_merge($payment->toArray(), ['lines' => $processedLines]),
            ]);

            if ($autoPost) {
                $this->post($payment, $data['created_by'], $processedLines);
            }

            return $payment->fresh();
        });
    }

    /**
     * Post a payment voucher and auto-generate linked journal entry.
     */
    public function post(Payment $payment, int $userId, array $lines = []): Payment
    {
        return DB::transaction(function () use ($payment, $userId, $lines) {
            if ($payment->status === 'POSTED') {
                throw new Exception('سند الصرف معتمد بالفعل.');
            }

            $journalService = app(JournalEntryService::class);

            $jeLines = [];
            // 1. Debit lines (Multi-party)
            if (! empty($lines)) {
                foreach ($lines as $l) {
                    $jeLines[] = [
                        'account_id' => $l['account_id'],
                        'description' => $l['description'] ?? ('صرف لحساب بموجب سند '.$payment->payment_number),
                        'debit' => (float) $l['amount'],
                        'credit' => 0,
                        'exchange_rate' => $payment->exchange_rate,
                    ];
                }
            } else {
                $jeLines[] = [
                    'account_id' => $payment->account_id,
                    'description' => 'صرف إلى حساب بموجب سند '.$payment->payment_number,
                    'debit' => $payment->amount,
                    'credit' => 0,
                    'exchange_rate' => $payment->exchange_rate,
                ];
            }

            // 2. Credit Cash/Bank for full amount
            $jeLines[] = [
                'account_id' => $payment->cash_or_bank_account_id,
                'description' => 'صرف نقدية/بنك بموجب سند '.$payment->payment_number,
                'debit' => 0,
                'credit' => $payment->amount,
                'exchange_rate' => $payment->exchange_rate,
            ];

            $journalEntry = $journalService->create([
                'company_id' => $payment->company_id,
                'branch_id' => $payment->branch_id,
                'entry_date' => $payment->payment_date->toDateString(),
                'description' => $payment->description ?? 'سند صرف رقم '.$payment->payment_number,
                'reference' => $payment->payment_number,
                'currency_id' => $payment->currency_id,
                'exchange_rate' => $payment->exchange_rate,
                'source_type' => 'PAYMENT',
                'source_id' => $payment->id,
                'created_by' => $userId,
                'lines' => $jeLines,
            ], autoPost: true);

            $payment->update([
                'status' => 'POSTED',
                'journal_entry_id' => $journalEntry->id,
            ]);

            AuditLog::create([
                'company_id' => $payment->company_id,
                'user_id' => $userId,
                'action' => 'POST_PAYMENT_VOUCHER',
                'auditable_type' => Payment::class,
                'auditable_id' => $payment->id,
                'new_values' => ['status' => 'POSTED', 'journal_entry_id' => $journalEntry->id],
            ]);

            return $payment;
        });
    }
}
