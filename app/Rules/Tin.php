<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Tax Identification Number (session 18): digits, with hyphens or spaces
 * allowed between them (for example 12345678-0001), 8 to 15 digits. Kept
 * loose on purpose: TINs have come in several lengths and layouts, and
 * NRS does the real check. Empty is fine.
 */
class Tin implements ValidationRule
{
    public static function isValid(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return true;
        }
        if (! preg_match('/^\d[\d\- ]*\d$/', $value) || preg_match('/[- ]{2}/', $value)) {
            return false;
        }
        $digits = strlen(preg_replace('/\D/', '', $value) ?? '');

        return $digits >= 8 && $digits <= 15;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid(is_scalar($value) ? (string) $value : '')) {
            $fail('Enter the TIN with digits only (hyphens are fine), 8 to 15 digits, for example 12345678-0001.');
        }
    }
}
