<?php

namespace App\Support;

/**
 * An amount written out in words, the way a payment voucher or a cheque
 * carries it ("One Hundred and Fifty Thousand Naira, Fifty Kobo Only").
 * Nigerian vouchers are normally signed against the written amount, so the
 * figure cannot be altered afterwards without the words disagreeing.
 */
final class AmountInWords
{
    /** @var array<int, string> */
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    /** @var array<int, string> */
    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /** @var array<int, string> */
    private const SCALES = [1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1_000 => 'Thousand'];

    /** The names of the major and minor units of a currency. */
    private const UNITS = [
        'NGN' => ['Naira', 'Kobo'],
        'USD' => ['Dollars', 'Cents'],
        'GBP' => ['Pounds', 'Pence'],
        'EUR' => ['Euro', 'Cents'],
        'GHS' => ['Cedis', 'Pesewas'],
        'KES' => ['Shillings', 'Cents'],
        'ZAR' => ['Rand', 'Cents'],
    ];

    /**
     * The amount in words, ending in "Only" as a voucher does.
     *
     * The minor unit is included only when there is one, so a round amount
     * reads "Fifty Thousand Naira Only" rather than "... Zero Kobo Only".
     */
    public static function format(float|int|string|null $amount, ?string $currency = null): string
    {
        $minor = Money::toMinor($amount);
        $negative = $minor < 0;
        $minor = abs($minor);

        [$major, $sub] = self::UNITS[$currency ?? Money::currency()] ?? ['', ''];

        $whole = intdiv($minor, 100);
        $part = $minor % 100;

        $words = self::words($whole).($major !== '' ? ' '.$major : '');
        if ($part > 0) {
            $words .= ', '.self::words($part).($sub !== '' ? ' '.$sub : '');
        }

        return ($negative ? 'Minus ' : '').$words.' Only';
    }

    /** A whole number in words, with "and" where English puts it. */
    public static function words(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }
        if ($number < 0) {
            return 'Minus '.self::words(-$number);
        }

        foreach (self::SCALES as $value => $name) {
            if ($number >= $value) {
                $left = intdiv($number, $value);
                $rest = $number % $value;
                // "and" joins only a remainder below one hundred ("one
                // thousand and fifty"); a larger remainder is a group of
                // its own, separated by a comma as a cheque writes it
                // ("two million, five hundred thousand").
                $join = $rest === 0 ? '' : ($rest < 100 ? ' and ' : ', ');

                return self::words($left).' '.$name.$join.($rest === 0 ? '' : self::words($rest));
            }
        }

        if ($number >= 100) {
            $rest = $number % 100;

            return self::ONES[intdiv($number, 100)].' Hundred'.($rest === 0 ? '' : ' and '.self::words($rest));
        }

        if ($number >= 20) {
            $rest = $number % 10;

            return self::TENS[intdiv($number, 10)].($rest === 0 ? '' : '-'.self::ONES[$rest]);
        }

        return self::ONES[$number];
    }
}
