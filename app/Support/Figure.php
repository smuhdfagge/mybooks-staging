<?php

namespace App\Support;

/**
 * How a report shows a figure (tables plan T6): two decimals with
 * thousands separators, nothing else. Negatives in brackets on financial
 * statements, zero as a dash. The currency is said once, in the page
 * description ("Amounts in ₦").
 */
final class Figure
{
    public static function show(float|int|string|null $value, bool $brackets = true): string
    {
        $v = round((float) $value, 2);
        if (abs($v) < 0.005) {
            return '—';
        }
        $text = number_format(abs($v), 2);

        return $v < 0 ? ($brackets ? "({$text})" : "-{$text}") : $text;
    }

    /** A quantity: no trailing zeros ("3", "2.5"), up to four decimals, zero as a dash. */
    public static function qty(float|int|string|null $value): string
    {
        $v = round((float) $value, 4);
        if (abs($v) < 0.00005) {
            return '—';
        }

        return rtrim(rtrim(number_format($v, 4), '0'), '.');
    }

    /** A rate as "7.5%" (no trailing zeros). */
    public static function rate(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
    }

    /** The cell class for a figure: muted when it is zero. */
    public static function tone(float|int|string|null $value): string
    {
        return abs(round((float) $value, 2)) < 0.005 ? 'tbl-zero' : '';
    }

    /** A share of a whole as "12.5%", or a dash when there is no whole. */
    public static function percent(float|int|string|null $part, float|int|string|null $whole, int $decimals = 1): string
    {
        $w = (float) $whole;

        return abs($w) < 0.005 ? '—' : number_format((float) $part / $w * 100, $decimals).'%';
    }
}
