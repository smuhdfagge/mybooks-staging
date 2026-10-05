<?php

namespace App\Enums;

/**
 * Assembly order statuses (session 14). A draft is a plan and moves
 * nothing; completing it takes the components out and puts the finished
 * goods in. A draft can be cancelled. "Undo build" takes a completed order
 * back to draft (components back in, finished goods out), as long as the
 * finished goods are still in stock.
 */
enum AssemblyOrderStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Completed, self::Cancelled],
            self::Completed => [self::Draft],
            self::Cancelled => [],
        };
    }
}
