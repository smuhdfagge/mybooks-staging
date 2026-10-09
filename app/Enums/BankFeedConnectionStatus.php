<?php

namespace App\Enums;

/**
 * Where a linked bank account stands (session 17). Not a document, so no
 * transition guard: a webhook can say "log in again" at any time.
 */
enum BankFeedConnectionStatus: string
{
    case Pending = 'pending';
    case Linked = 'linked';
    case NeedsReauthorisation = 'needs_reauthorisation';
    case Error = 'error';
    case Unlinked = 'unlinked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for the bank',
            self::Linked => 'Connected',
            self::NeedsReauthorisation => 'Needs you to log in again',
            self::Error => 'Problem',
            self::Unlinked => 'Disconnected',
        };
    }

    /** Statuses that use up one of the plan's linked accounts. */
    public static function counted(): array
    {
        return [self::Linked->value, self::NeedsReauthorisation->value, self::Error->value];
    }

    /** Statuses from which the scheduled sync pulls transactions. */
    public static function syncable(): array
    {
        return [self::Linked->value, self::Error->value];
    }
}
