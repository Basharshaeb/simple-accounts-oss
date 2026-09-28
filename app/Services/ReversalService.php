<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Facades\DB;

class ReversalService
{
    /**
     * Reverse a posted journal entry by creating an opposite entry.
     */
    public function reverseJournalEntry(JournalEntry $entry, int $userId, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId, $reason) {
            if ($entry->status !== 'POSTED') {
                throw new Exception('يمكن فقط إلغاء القيود المعتمدة (POSTED).');
            }

            if ($entry->fiscalPeriod->status === 'CLOSED') {
                throw new Exception('لا يمكن إلغاء قيد في فترة مالية مغلقة.');
            }

            $journalService = app(JournalEntryService::class);

            $reversedLines = [];
            foreach ($entry->lines as $line) {
                $reversedLines[] = [
                    'account_id' => $line->account_id,
                    'description' => 'إلغاء: '.($line->description ?? $entry->description),
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'exchange_rate' => $line->exchange_rate,
                ];
            }

            $reversalReason = $reason ? " ({$reason})" : '';

            $reversalEntry = $journalService->create([
                'company_id' => $entry->company_id,
                'entry_date' => $entry->entry_date->toDateString(),
                'description' => "قيد عكسي للقيد رقم {$entry->entry_number}{$reversalReason}",
                'reference' => "REVERSAL-{$entry->entry_number}",
                'currency_id' => $entry->currency_id,
                'exchange_rate' => $entry->exchange_rate,
                'lines' => $reversedLines,
                'source_type' => 'REVERSAL',
                'source_id' => $entry->id,
                'created_by' => $userId,
            ], autoPost: true);

            $entry->update([
                'status' => 'REVERSED',
            ]);

            AuditLog::create([
                'company_id' => $entry->company_id,
                'user_id' => $userId,
                'action' => 'REVERSE_JOURNAL',
                'auditable_type' => JournalEntry::class,
                'auditable_id' => $entry->id,
                'old_values' => ['status' => 'POSTED'],
                'new_values' => ['status' => 'REVERSED', 'reversal_entry_id' => $reversalEntry->id],
            ]);

            return $reversalEntry;
        });
    }
}
