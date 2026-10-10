<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;

/**
 * The dates a dashboard shows and the dates it compares them with.
 *
 * Comparisons are like for like: "this month" on 10 October is 1–10 October,
 * compared with 1–10 September, not with all of September.
 */
final class DashboardPeriod
{
    public const PERIODS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'this_year' => 'This financial year',
        'last_12_months' => 'Last 12 months',
    ];

    public const COMPARES = [
        'previous' => 'Period before',
        'last_year' => 'Same time last year',
        'none' => 'No comparison',
    ];

    private function __construct(
        public readonly string $key,
        public readonly string $compareKey,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?Carbon $compareFrom,
        public readonly ?Carbon $compareTo,
    ) {}

    /**
     * @param  Carbon  $yearStart  start of the business's financial year that contains today
     */
    public static function make(?string $key, ?string $compare, Carbon $today, Carbon $yearStart): self
    {
        $key = array_key_exists((string) $key, self::PERIODS) ? (string) $key : 'this_month';
        $compare = array_key_exists((string) $compare, self::COMPARES) ? (string) $compare : 'previous';
        $today = $today->copy()->startOfDay();

        [$from, $to] = match ($key) {
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today->copy()],
            'this_year' => [$yearStart->copy()->startOfDay(), $today->copy()],
            'last_12_months' => [$today->copy()->subMonthsNoOverflow(11)->startOfMonth(), $today->copy()],
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };

        [$cFrom, $cTo] = match ($compare) {
            'none' => [null, null],
            'last_year' => [$from->copy()->subYearNoOverflow(), $to->copy()->subYearNoOverflow()],
            default => self::previous($key, $from, $to),
        };

        return new self($key, $compare, $from, $to, $cFrom, $cTo);
    }

    /** The same stretch of the period before, e.g. 1–10 Sep for 1–10 Oct. */
    private static function previous(string $key, Carbon $from, Carbon $to): array
    {
        $months = match ($key) {
            'this_quarter' => 3,
            'this_year', 'last_12_months' => 12,
            default => 1,
        };
        $cFrom = $from->copy()->subMonthsNoOverflow($months);
        // Same number of days into the period, but never past its end.
        $cTo = $cFrom->copy()->addDays($from->diffInDays($to));
        $periodEnd = $from->copy()->subDay();
        if ($key === 'last_month') {
            $cTo = $cFrom->copy()->endOfMonth()->startOfDay();
        } elseif ($cTo->greaterThan($periodEnd)) {
            $cTo = $periodEnd;
        }

        return [$cFrom, $cTo];
    }

    public function label(): string
    {
        return self::PERIODS[$this->key].' ('.self::range($this->from, $this->to).')';
    }

    public function compareLabel(): ?string
    {
        return $this->compareFrom ? self::range($this->compareFrom, $this->compareTo) : null;
    }

    public function cacheKey(): string
    {
        return $this->from->toDateString().'_'.$this->to->toDateString().'_'.($this->compareFrom?->toDateString() ?? '-').'_'.($this->compareTo?->toDateString() ?? '-');
    }

    /** "1–10 Oct", "1 Sep – 10 Oct", "1 Nov 2025 – 10 Oct 2026". */
    public static function range(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('j M Y');
        }
        if ($from->year !== $to->year) {
            return $from->format('j M Y').' – '.$to->format('j M Y');
        }
        if ($from->month === $to->month) {
            return $from->format('j').'–'.$to->format('j M');
        }

        return $from->format('j M').' – '.$to->format('j M');
    }
}
