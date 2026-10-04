<?php

namespace App\Services\Accounting;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\AccountingPeriod;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Lock dates (session 11), the one place that says whether a date can be
 * changed. Like Xero:
 * - staff lock date: nothing dated on or before it can be created, edited,
 *   deleted, voided or posted, except by users with "override lock-date"
 *   (admins, accountants), who see a warning instead;
 * - all-users lock date: nobody at all, admins included. It is never later
 *   than the staff lock date.
 * Closed and locked accounting periods are checked here too, so every model
 * (ValidatesAccountingPeriod) and every automatic posting (firstOpenDate)
 * goes through the same rules.
 */
class LockDates
{
    public const STAFF = 'staff';

    public const ALL_USERS = 'all_users';

    /** History entries that aren't lock date moves. */
    public const PERIOD = 'period';

    public const VAT_RETURN = 'vat_return';

    public const OVERRIDE_PERMISSION = 'override lock-date';

    public const MANAGE_PERMISSION = 'manage lock-dates';

    /** @var array<int, array{staff: ?Carbon, all_users: ?Carbon}> */
    private array $cache = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    public static function enabled(): bool
    {
        return EnsureFeatureEnabled::enabled('lock_dates');
    }

    /**
     * The business's two lock dates (null when not set or the feature is off).
     *
     * @return array{staff: ?Carbon, all_users: ?Carbon}
     */
    public function dates(int $tenantId): array
    {
        if (! self::enabled()) {
            return ['staff' => null, 'all_users' => null];
        }

        return $this->cache[$tenantId] ??= (function () use ($tenantId) {
            $row = DB::table('tenants')->where('id', $tenantId)->first(['staff_lock_date', 'all_users_lock_date']);

            return [
                'staff' => $row?->staff_lock_date ? Carbon::parse($row->staff_lock_date)->startOfDay() : null,
                'all_users' => $row?->all_users_lock_date ? Carbon::parse($row->all_users_lock_date)->startOfDay() : null,
            ];
        })();
    }

    public function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$tenantId]);
        }
    }

    public static function canOverride(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->can(self::OVERRIDE_PERMISSION);
    }

    /**
     * The last locked day for this user: the all-users date for someone who
     * can override, otherwise the later of the two. Null when nothing is locked.
     */
    public function lockedUpTo(int $tenantId, ?Authenticatable $user = null): ?Carbon
    {
        $dates = $this->dates($tenantId);
        if (self::canOverride($user)) {
            return $dates['all_users'];
        }

        return $this->latest($dates);
    }

    /** The later of the two dates: what automatic postings (nobody signed in) must stay after. */
    public function latestLock(int $tenantId): ?Carbon
    {
        return $this->latest($this->dates($tenantId));
    }

    /**
     * Why $date can't be changed by $user, or null when it can. A closed
     * accounting period comes first (its own message), then the all-users
     * lock, then the staff lock unless the user may override it.
     */
    public function blockReason(mixed $date, int $tenantId, ?Authenticatable $user = null): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $day = Carbon::parse($date)->startOfDay();

        if (AccountingPeriod::isDateInClosedPeriod($day, $tenantId)) {
            return AccountingPeriod::getClosedPeriodMessage($day, $tenantId);
        }

        return $this->lockReason($day, $tenantId, $user);
    }

    /** As blockReason(), lock dates only (no accounting periods). */
    public function lockReason(mixed $date, int $tenantId, ?Authenticatable $user = null): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $day = Carbon::parse($date)->startOfDay();
        $dates = $this->dates($tenantId);

        if ($dates['all_users'] && $day->lte($dates['all_users'])) {
            return "The books are locked for everyone up to {$dates['all_users']->format('j M Y')}. "
                .'An admin must move the lock date back, with a reason, before this can be changed.';
        }
        if ($dates['staff'] && $day->lte($dates['staff']) && ! self::canOverride($user)) {
            return "The books are locked up to {$dates['staff']->format('j M Y')}. "
                .'Ask an admin to change the lock date if you need to change this.';
        }

        return null;
    }

    /**
     * For a user who can override the staff lock: a warning when $date is on
     * or before it (they can still save). Null otherwise.
     */
    public function warning(mixed $date, int $tenantId, ?Authenticatable $user = null): ?string
    {
        if ($date === null || $date === '' || ! self::canOverride($user)) {
            return null;
        }
        $day = Carbon::parse($date)->startOfDay();
        $dates = $this->dates($tenantId);
        if (! $dates['staff'] || $day->gt($dates['staff']) || ($dates['all_users'] && $day->lte($dates['all_users']))) {
            return null;
        }

        return "This date is on or before the lock date ({$dates['staff']->format('j M Y')}). "
            .'You can still save it because you are allowed to override the lock; other staff cannot.';
    }

    /**
     * The first date on or after $date that an automatic posting can use:
     * after both lock dates and not in a closed or locked period.
     */
    public function firstOpenDate(int $tenantId, CarbonInterface $date): CarbonInterface
    {
        $day = $date->copy()->startOfDay();
        $lock = $this->latestLock($tenantId);
        if ($lock && $day->lte($lock)) {
            $day = $lock->copy()->addDay()->startOfDay();
        }
        // Each pass steps past one closed period; a period only moves the
        // date later, so the lock dates can't apply again.
        for ($i = 0; $i < 500; $i++) {
            $period = AccountingPeriod::getPeriodForDate($day, $tenantId);
            if (! $period || ! $period->isClosed()) {
                break;
            }
            $day = Carbon::parse($period->end_date)->addDay()->startOfDay();
        }

        return $day;
    }

    /**
     * The note on an automatic posting moved from $due to $postedOn, e.g.
     * "due 31 Jan 2026, but that period is closed, so posted on 1 Mar 2026".
     */
    public function movedNote(int $tenantId, CarbonInterface $due, CarbonInterface $postedOn): ?string
    {
        if ($postedOn->isSameDay($due)) {
            return null;
        }
        $lock = $this->latestLock($tenantId);
        $why = ! AccountingPeriod::isDateInClosedPeriod($due, $tenantId) && $lock && $due->lte($lock)
            ? "the books are locked up to {$lock->format('j M Y')}"
            : 'that period is closed';

        return "due {$due->format('j M Y')}, but {$why}, so posted on {$postedOn->format('j M Y')}";
    }

    /** @param  array{staff: ?Carbon, all_users: ?Carbon}  $dates */
    private function latest(array $dates): ?Carbon
    {
        if ($dates['staff'] && $dates['all_users']) {
            return $dates['staff']->gt($dates['all_users']) ? $dates['staff'] : $dates['all_users'];
        }

        return $dates['staff'] ?? $dates['all_users'];
    }
}
