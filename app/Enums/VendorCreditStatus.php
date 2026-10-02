<?php

namespace App\Enums;

/**
 * Supplier credit note statuses. Draft posts nothing; open is posted and
 * can be used against bills or refunded; closed is fully used; void is
 * final (its journal and stock return are reversed).
 */
enum VendorCreditStatus: string implements DocumentStatus
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
            self::Closed => [self::Open],
            self::Void => [],
        };
    }

    /** @return array<int, string> */
    public static function startValues(): array
    {
        return [self::Draft->value, self::Open->value];
    }
}
