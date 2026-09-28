<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Facades\DB;

class PostingService
{
    /**
     * Post a draft journal entry.
     */
    public function postJournalEntry(JournalEntry $entry, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId) {
            if ($entry->status === 'POSTED') {
                throw new Exception('هذا القيد معتمد بالفعل.');
            }

            if ($entry->status === 'REVERSED') {
                throw new Exception('لا يمكن اعتماد قيد تم إلغاؤه (Reversed).');
            }

            // Check fiscal period
            if ($entry->fiscalPeriod->status === 'CLOSED') {
                throw new Exception('الفترة المالية الخاصة بهذا القيد مغلقة.');
            }

            // Check double entry balance
            $sumDebit = $entry->lines()->sum('base_debit');
            $sumCredit = $entry->lines()->sum('base_credit');

            if (abs($sumDebit - $sumCredit) > 0.0001) {
                throw new Exception("القيد رقم {$entry->entry_number} غير متوازن ولن يتم اعتماده.");
            }

            $oldStatus = $entry->status;

            $entry->update([
                'status' => 'POSTED',
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            AuditLog::create([
                'company_id' => $entry->company_id,
                'user_id' => $userId,
                'action' => 'POST_JOURNAL',
                'auditable_type' => JournalEntry::class,
                'auditable_id' => $entry->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'POSTED', 'posted_by' => $userId, 'posted_at' => now()->toIso8601String()],
            ]);

            return $entry;
        });
    }
}
