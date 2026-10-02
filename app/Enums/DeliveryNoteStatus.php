<?php

namespace App\Enums;

/**
 * Delivery note statuses. A draft is prepared; dispatching it counts the
 * goods as delivered on the sales order; delivered records who received
 * them. A draft or dispatched note can be cancelled, which takes the
 * quantities off the order again. Delivered and cancelled are final.
 * (in_transit is an old value, treated like dispatched.)
 */
enum DeliveryNoteStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Dispatched, self::Cancelled],
            self::Dispatched, self::InTransit => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    /** Statuses in which the goods count as delivered on the order. @return array<int, string> */
    public static function countedValues(): array
    {
        return [self::Dispatched->value, self::InTransit->value, self::Delivered->value];
    }
}
