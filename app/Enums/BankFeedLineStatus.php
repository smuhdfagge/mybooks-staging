<?php

namespace App\Enums;

/**
 * What has been done with a bank line (session 17).
 * New lines wait for a click; nothing posts by itself.
 */
enum BankFeedLineStatus: string
{
    case New = 'new';
    case Matched = 'matched';
    case Created = 'created';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::New => 'To review',
            self::Matched => 'Matched',
            self::Created => 'Recorded',
            self::Ignored => 'Ignored',
        };
    }
}
