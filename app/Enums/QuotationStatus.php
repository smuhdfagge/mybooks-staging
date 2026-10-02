<?php

namespace App\Enums;

/**
 * Quotation statuses and the moves between them.
 *
 * A quotation starts as a draft, is sent, and the customer accepts or
 * rejects it. Past its expiry date it is marked expired (by the daily
 * quotations:expire command); editing an expired or rejected quotation
 * reopens it as a draft. Converted (to a sales order or an invoice) is final.
 */
enum QuotationStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Accepted, self::Rejected, self::Expired, self::Converted],
            self::Sent => [self::Accepted, self::Rejected, self::Expired, self::Converted],
            self::Accepted => [self::Converted],
            self::Rejected => [self::Draft],
            self::Expired => [self::Draft, self::Sent, self::Accepted, self::Rejected],
            self::Converted => [],
        };
    }

    /** Statuses in which the quotation can still be changed. @return array<int, string> */
    public static function editableValues(): array
    {
        return [self::Draft->value, self::Sent->value, self::Rejected->value, self::Expired->value];
    }

    /** Statuses from which it can be converted to an order or invoice. @return array<int, string> */
    public static function convertibleValues(): array
    {
        return [self::Draft->value, self::Sent->value, self::Accepted->value];
    }
}
