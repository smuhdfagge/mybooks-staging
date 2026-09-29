<?php

namespace App\Enums;

/**
 * Invoice statuses and the moves between them (finding Q3).
 *
 * Draft is where an invoice starts. Sent, unpaid, partial, paid and
 * overdue follow from sending, payments and due dates, so they move among
 * themselves freely. Cancelled is final, and nothing goes back to draft.
 */
enum InvoiceStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case Sent = 'sent';
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        $open = [self::Sent, self::Unpaid, self::Partial, self::Paid, self::Overdue, self::Cancelled];

        return match ($this) {
            self::Draft => $open,
            self::Cancelled => [],
            default => $open,
        };
    }

    /** Statuses a new invoice may start in. @return array<int, string> */
    public static function startValues(): array
    {
        return [self::Draft->value, self::Sent->value, self::Unpaid->value];
    }
}
