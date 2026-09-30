<?php

namespace App\Support;

/**
 * Money arithmetic in one place (finding Q2).
 *
 * Amounts are kept as floats in the models, but every sum here is done in
 * whole kobo (cents), so adding many amounts can't drift by a fraction
 * of a kobo, and every result is rounded to 2 decimals the same way
 * (half away from zero). The rule for documents is: round each line, then
 * add the rounded lines.
 */
final class Money
{
    /** Round to 2 decimals, half away from zero. */
    public static function round(float|int|string|null $amount): float
    {
        return self::fromMinor(self::toMinor($amount));
    }

    /**
     * Sum amounts after rounding each one.
     *
     * @param  iterable<float|int|string|null>  $amounts
     */
    public static function sum(iterable $amounts): float
    {
        $minor = 0;
        foreach ($amounts as $amount) {
            $minor += self::toMinor($amount);
        }

        return self::fromMinor($minor);
    }

    /** $a + $b - $c ... with each part rounded first. */
    public static function add(float|int|string|null ...$amounts): float
    {
        return self::sum($amounts);
    }

    public static function subtract(float|int|string|null $from, float|int|string|null ...$amounts): float
    {
        $minor = self::toMinor($from);
        foreach ($amounts as $amount) {
            $minor -= self::toMinor($amount);
        }

        return self::fromMinor($minor);
    }

    /** $rate percent of $amount, rounded. */
    public static function percent(float|int|string|null $amount, float|int|string|null $rate): float
    {
        return self::round((float) $amount * (float) $rate / 100);
    }

    /** Equal to the kobo. */
    public static function equals(float|int|string|null $a, float|int|string|null $b): bool
    {
        return self::toMinor($a) === self::toMinor($b);
    }

    /**
     * Share $total across $weights in proportion, in whole kobo, so the
     * shares always add up to $total exactly. The last share takes any
     * rounding difference.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, float|int|string>  $weights
     * @return array<TKey, float>
     */
    public static function allocate(float|int|string $total, array $weights): array
    {
        $totalMinor = self::toMinor($total);
        $weightSum = array_sum(array_map('floatval', $weights));
        $shares = [];
        $left = $totalMinor;
        $keys = array_keys($weights);
        $last = end($keys);

        foreach ($weights as $key => $weight) {
            if ($key === $last) {
                $shares[$key] = self::fromMinor($left);
            } else {
                $minor = $weightSum > 0 ? (int) round($totalMinor * (float) $weight / $weightSum) : 0;
                $shares[$key] = self::fromMinor($minor);
                $left -= $minor;
            }
        }

        return $shares;
    }

    /**
     * Show an amount for people to read (U8): currency symbol, thousands
     * separators and 2 decimals, rounded as above. Uses the signed-in
     * business's currency unless one is given. In Blade: @money($amount).
     */
    public static function format(float|int|string|null $amount, ?string $currency = null): string
    {
        $minor = self::toMinor($amount);

        return ($minor < 0 ? '-' : '').self::symbol($currency).number_format(abs($minor) / 100, 2);
    }

    /** Symbol for a currency code (the business's by default). In Blade: @currencySymbol. */
    public static function symbol(?string $currency = null): string
    {
        $code = $currency ?? self::currency();
        $match = collect(config('mybooks.currencies', []))->firstWhere('code', $code);

        return $match['symbol'] ?? $code.' ';
    }

    /** The signed-in business's currency code. */
    public static function currency(): string
    {
        $user = auth()->user();
        $tenant = $user instanceof \App\Models\User ? $user->tenant : null;

        return $tenant->currency ?? 'NGN';
    }

    /** Whole kobo (cents). */
    public static function toMinor(float|int|string|null $amount): int
    {
        // Rounding the kobo figure to 4 places first removes binary noise
        // (1.005 * 100 is 100.4999999...), so 1.005 rounds up as written.
        return (int) round(round((float) $amount * 100, 4));
    }

    public static function fromMinor(int $minor): float
    {
        return $minor / 100;
    }
}
