<?php

namespace App\Enums;

/**
 * Where an SMS / WhatsApp message to a customer has got to (session 16).
 *
 * Not a document, so no transition guard: delivery reports can arrive late
 * or out of order. CustomerMessage::markFromProvider() never moves a
 * delivered message back.
 */
enum MessageStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';

    /** Statuses that use up the monthly allowance (failed ones don't). */
    public static function counted(): array
    {
        return [self::Queued->value, self::Sending->value, self::Sent->value, self::Delivered->value];
    }
}
