<?php

namespace App\Enums;

/**
 * Prepaid expense / deferred revenue schedule statuses (S9). An active
 * schedule releases one month at a time; when the last month is released
 * it is completed. Cancelling stops the months not yet released.
 */
enum AccrualScheduleStatus: string implements DocumentStatus
{
    use StatusTransitions;

    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Active => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }
}
