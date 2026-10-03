<?php

namespace App\Enums;

/**
 * Customer credit note statuses. A draft posts nothing. Opening it posts
 * the credit (and returns any goods to stock); its balance is then applied
 * to invoices or refunded, and when nothing is left it is closed. An open
 * credit note that hasn't been used can be voided, which reverses it.
 */
enum CreditNoteStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Void = 'void';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Open, self::Void],
            self::Open => [self::Closed, self::Void],
            self::Closed, self::Void => [],
        };
    }

    /** A new credit note is saved as a draft or opened straight away. @return array<int, string> */
    public static function startValues(): array
    {
        return [self::Draft->value, self::Open->value];
    }
}
