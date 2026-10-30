<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * A PHP value as a JavaScript literal, behind @js and @jsText (audit MB-29).
 *
 * Laravel's Js::from() writes arrays, objects and strings as
 * JSON.parse('...'), and the CSP build of Alpine can't call JSON (no
 * globals). This writes the literal itself: 'single-quoted' strings and
 * keys, numbers in plain decimals, true/false/null, [..] and {..}. Every
 * quote, <, >, &, backslash and control character inside a string is a
 * \uXXXX escape, so the output is safe in a double-quoted HTML attribute
 * and inside <script>, and nothing in a value can end the string early.
 */
class JsLiteral
{
    public static function from(mixed $data): string
    {
        // Through JSON first, so collections, models and JsonSerializable
        // objects become what they would in Js::from().
        if ($data instanceof Jsonable) {
            $data = json_decode($data->toJson(), false, 512, JSON_THROW_ON_ERROR);
        } elseif ($data instanceof Arrayable || $data instanceof JsonSerializable || is_object($data) || is_array($data)) {
            $data = json_decode(json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), false, 512, JSON_THROW_ON_ERROR);
        }

        return self::write($data);
    }

    private static function write(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::number($value),
            is_string($value) => self::string($value),
            is_array($value) => '['.implode(',', array_map(self::write(...), $value)).']',
            default => '{'.implode(',', array_map(
                fn ($key, $item) => self::string((string) $key).':'.self::write($item),
                array_keys((array) $value),
                (array) $value,
            )).'}',
        };
    }

    /** Plain decimals: the CSP parser reads no exponent (1e-7). */
    private static function number(float $value): string
    {
        if (! is_finite($value)) {
            return 'null';
        }
        $text = json_encode($value);
        if (stripos((string) $text, 'e') !== false) {
            $text = rtrim(rtrim(number_format($value, 20, '.', ''), '0'), '.');
        }

        return (string) $text;
    }

    private static function string(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $out = "'";
        foreach (mb_str_split($value) as $char) {
            $code = mb_ord($char, 'UTF-8');
            $out .= ($code < 0x20 || $code === 0x7F || str_contains("\"'<>&\\`", $char) || $code === 0x2028 || $code === 0x2029)
                ? sprintf('\\u%04x', $code)
                : $char;
        }

        return $out."'";
    }
}
