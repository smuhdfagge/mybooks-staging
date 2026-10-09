<?php

namespace App\Support;

/**
 * Phone numbers for SMS and WhatsApp (session 16).
 *
 * Nigerian mobiles are written many ways (0803 123 4567, 803-123-4567,
 * 2348031234567, +234 (0) 803...); they all become +2348031234567. Mobile
 * numbers start 070, 071, 080, 081, 090 or 091 after the 0. Numbers for other
 * countries must start with + (or 00) and the country code.
 */
class PhoneNumber
{
    private const NG_MOBILE = '/^[789][01]\d{8}$/';

    /** The number in +country form, or null if it can't take SMS / WhatsApp. */
    public static function normalise(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || preg_match('/[a-z]/i', $raw)) {
            return null;
        }

        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }

        if ($international || (str_starts_with($digits, '234') && strlen($digits) >= 13)) {
            if (str_starts_with($digits, '234')) {
                $national = ltrim(substr($digits, 3), '0');

                return preg_match(self::NG_MOBILE, $national) ? '+234'.$national : null;
            }

            // Other countries: E.164 allows up to 15 digits.
            return $international && strlen($digits) >= 8 && strlen($digits) <= 15 && $digits[0] !== '0' ? '+'.$digits : null;
        }

        // Local Nigerian forms: 08031234567, or 8031234567 without the 0.
        $national = strlen($digits) === 11 && $digits[0] === '0' ? substr($digits, 1) : $digits;

        return preg_match(self::NG_MOBILE, $national) ? '+234'.$national : null;
    }

    /**
     * Fine for the phone field: a number that can take SMS, or at least
     * looks like a phone number (a landline, say).
     */
    public static function looksValid(string $raw): bool
    {
        if (self::normalise($raw) !== null) {
            return true;
        }
        $digits = strlen(preg_replace('/\D+/', '', $raw));

        return (bool) preg_match('/^[0-9+\-\s().\/]+$/', $raw) && $digits >= 7 && $digits <= 15;
    }

    /** +234 803 *** 4567, for lists. */
    public static function mask(?string $number): string
    {
        $number = (string) $number;
        if (preg_match('/^\+234(\d{3})\d{3}(\d{4})$/', $number, $m)) {
            return "+234 {$m[1]} *** {$m[2]}";
        }

        return strlen($number) > 8 ? substr($number, 0, 4).' *** '.substr($number, -4) : '***';
    }
}
