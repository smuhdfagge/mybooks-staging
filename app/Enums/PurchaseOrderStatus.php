<?php

namespace App\Enums;

/**
 * Purchase order statuses and the moves between them (finding Q3).
 * Receiving moves it through partially received and received; billing
 * marks it billed, and deleting that bill makes it billable again.
 * Cancelled is final.
 */
enum PurchaseOrderStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Billed = 'billed';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::PartiallyReceived, self::Received, self::Billed, self::Cancelled],
            self::PartiallyReceived => [self::Confirmed, self::Received, self::Billed, self::Cancelled],
            self::Received => [self::PartiallyReceived, self::Billed],
            self::Billed => [self::Confirmed, self::PartiallyReceived, self::Received],
            self::Cancelled => [],
        };
    }

    /** Statuses a bill can be raised from. @return array<int, string> */
    public static function billableValues(): array
    {
        return [self::Confirmed->value, self::PartiallyReceived->value, self::Received->value];
    }
}
