<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Receipt;

class DocumentNumberingService
{
    /**
     * Generate the next document number for a company and year.
     * Prefix examples: JE, RV, PV
     */
    public static function generate(int $companyId, string $prefix, int $year): string
    {
        $pattern = "{$prefix}-{$year}-%";

        $numbers = match ($prefix) {
            'JE' => JournalEntry::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('entry_number', 'like', $pattern)
                ->pluck('entry_number'),
            'RV' => Receipt::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('receipt_number', 'like', $pattern)
                ->pluck('receipt_number'),
            'PV' => Payment::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('payment_number', 'like', $pattern)
                ->pluck('payment_number'),
            default => collect([]),
        };

        $maxSeq = 0;
        foreach ($numbers as $numStr) {
            $parts = explode('-', $numStr);
            $lastPart = end($parts);
            if (is_numeric($lastPart)) {
                $seq = (int) $lastPart;
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $nextSeq);
    }
}
