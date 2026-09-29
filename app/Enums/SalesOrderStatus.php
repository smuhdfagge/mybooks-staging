<?php

namespace App\Enums;

/**
 * Sales order statuses and the moves between them (finding Q3).
 * Processing and completed follow deliveries; invoiced follows
 * conversion. Cancelled is final.
 */
enum SalesOrderStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Invoiced = 'invoiced';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Invoiced, self::Completed, self::Cancelled],
            self::Processing => [self::Invoiced, self::Completed, self::Cancelled],
            self::Invoiced => [self::Processing, self::Completed, self::Confirmed],
            self::Completed => [self::Processing, self::Invoiced],
            self::Cancelled => [],
        };
    }
}
