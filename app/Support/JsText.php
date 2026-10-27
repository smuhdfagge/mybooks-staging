<?php

namespace App\Support;

/**
 * The text behind @jsText(): a value as a plain string for a JavaScript
 * string literal. Null, arrays and objects (e.g. ?customer_id[]=x) become
 * an empty string rather than an error or a JSON object.
 */
class JsText
{
    public static function of(mixed $value): string
    {
        if ($value instanceof \Stringable) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
