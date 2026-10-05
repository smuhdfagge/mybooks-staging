<?php

namespace App\Enums;

/**
 * Stock transfer statuses (session 13). A draft is planned and can be
 * deleted; shipping takes the goods out of the source warehouse (in
 * transit); receiving puts them in the destination. An in-transit transfer
 * can be cancelled, which puts the goods back where they came from.
 * Received and cancelled are final: to undo a received transfer, make one
 * the other way.
 */
enum StockTransferStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::InTransit],
            self::InTransit => [self::Received, self::Cancelled],
            self::Received, self::Cancelled => [],
        };
    }
}
