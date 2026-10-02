<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Can something be posted on a date? One place for the answer, used by
 * every posting path (ValidatesAccountingPeriod) and by the scheduled
 * commands that post on their own (auto-reversals, schedule releases).
 *
 * A date is closed when it falls in a closed or locked accounting period.
 */
class PostingLock
{
    /** Why nothing can be posted on $date, or null when it can. */
    public static function reasonFor(mixed $date, int $tenantId): ?string
    {
        $day = Carbon::parse($date)->startOfDay();

        if (AccountingPeriod::isDateInClosedPeriod($day, $tenantId)) {
            return AccountingPeriod::getClosedPeriodMessage($day, $tenantId);
        }

        return null;
    }

    /**
     * The first date on or after $date that can be posted to, for the
     * commands that post by themselves. Steps past closed periods.
     */
    public static function firstOpenDate(int $tenantId, mixed $date): CarbonInterface
    {
        $day = Carbon::parse($date)->startOfDay();

        // Each step moves past one closed period, so this ends quickly.
        for ($i = 0; $i < 400; $i++) {
            $period = AccountingPeriod::getPeriodForDate($day, $tenantId);
            if (! $period || ! $period->isClosed()) {
                return $day;
            }
            $day = Carbon::parse($period->end_date)->addDay()->startOfDay();
        }

        return $day;
    }
}
