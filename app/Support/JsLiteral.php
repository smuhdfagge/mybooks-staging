<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
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
    public static function from(mixed $data, int $flags = 0): string
    {
        if ($flags !== 0) {
            // Laravel's @js($value, JSON_...) flags don't apply to this format.
            throw new InvalidArgumentException('@js does not take JSON flags here: see App\\Support\\JsLiteral.');
        }

        // The same conversions as Js::from(), then through JSON, so
        // collections, models and JsonSerializable objects come out alike.
        if ($data instanceof Htmlable && ! $data instanceof Arrayable && ! $data instanceof Jsonable && ! $data instanceof JsonSerializable) {
            $data = $data->toHtml();
        }
        if ($data instanceof Arrayable && ! $data instanceof Jsonable && ! $data instanceof JsonSerializable) {
            $data = $data->toArray();
        }
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
        $text = (string) json_encode($value);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?e([+-]?\d+)$/i', $text, $m)) {
            return $text;
        }

        // 1.2345e-10 -> 0.00000000012345, exactly: move the point in the digits.
        [, $sign, $whole, $fraction, $exponent] = $m + [3 => ''];
        $digits = ltrim($whole.$fraction, '0');
        $point = strlen(ltrim($whole, '0')) + (int) $exponent;
        if ($digits === '') {
            return '0';
        }
        $digits = rtrim($digits, '0');
        if ($point <= 0) {
            return $sign.'0.'.str_repeat('0', -$point).$digits;
        }
        if ($point >= strlen($digits)) {
            return $sign.$digits.str_repeat('0', $point - strlen($digits));
        }

        return $sign.substr($digits, 0, $point).'.'.substr($digits, $point);
    }

    private static function string(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            // U+FFFD for bad bytes, as json_encode and Js::from() give.
            $value = (string) json_decode((string) json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE));
        }
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
