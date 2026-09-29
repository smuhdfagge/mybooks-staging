<?php

namespace App\Enums;

/**
 * Bill statuses and the moves between them (finding Q3). Unpaid, partial,
 * paid and overdue follow payments and due dates; cancelled is final;
 * nothing goes back to draft.
 */
enum BillStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Cancelled => [],
            default => [self::Unpaid, self::Partial, self::Paid, self::Overdue, self::Cancelled],
        };
    }

    /** @return array<int, string> */
    public static function startValues(): array
    {
        return [self::Draft->value, self::Unpaid->value];
    }
}
