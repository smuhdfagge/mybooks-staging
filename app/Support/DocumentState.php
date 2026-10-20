<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Where a document that is owed stands, in words: an invoice the customer
 * owes, or a bill owed to a supplier. Both move through the same states,
 * so they read them the same way and a change here reaches both.
 */
final class DocumentState
{
    /**
     * @param  string|null  $status  draft, cancelled, paid, partial or unpaid
     * @return array{key: string, label: string, daysLate: int}
     */
    public static function of(?string $status, float $balance, float $total, float $amountPaid, ?CarbonInterface $dueDate): array
    {
        $balance = round($balance, 2);
        $open = ! in_array($status, ['draft', 'cancelled', 'paid'], true) && $balance > 0;
        $daysLate = $open && $dueDate instanceof CarbonInterface && $dueDate->lt(now()->startOfDay())
            ? (int) $dueDate->diffInDays(now()->startOfDay())
            : 0;

        $key = match (true) {
            $status === 'draft' => 'draft',
            $status === 'cancelled' => 'cancelled',
            $balance <= 0 && $total > 0 => 'paid',
            $daysLate > 0 => 'overdue',
            $amountPaid > 0 => 'partial',
            default => 'open',
        };

        $label = [
            'draft' => 'Draft',
            'cancelled' => 'Cancelled',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'partial' => 'Part paid',
            'open' => 'Awaiting payment',
        ][$key];

        return ['key' => $key, 'label' => $label, 'daysLate' => $daysLate];
    }
}
