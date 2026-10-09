<?php

namespace App\Support;

/**
 * SMS length and pages (session 16).
 *
 * Plain text (the GSM-7 alphabet) fits 160 characters in one SMS, 153 per
 * page when longer; a few characters ({ } [ ] ~ \ | ^ €) count as two. Any
 * other character (an emoji, ₦, curly quotes) turns the whole SMS into
 * Unicode: 70 per SMS, 67 per page. Each page is charged, so clean() swaps
 * the usual look-alikes for plain ones first.
 */
class SmsText
{
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXTENDED = "^{}\\[~]|€\f";

    private const REPLACE = [
        '’' => "'", '‘' => "'", '‛' => "'", '′' => "'", '`' => "'",
        '“' => '"', '”' => '"', '″' => '"',
        '–' => '-', '—' => '-', '−' => '-', '…' => '...', '•' => '-',
        '₦' => 'N', "\u{00A0}" => ' ', "\t" => ' ',
    ];

    public static function clean(string $text): string
    {
        $text = strtr($text, self::REPLACE);

        return trim(preg_replace('/ {2,}/', ' ', $text));
    }

    public static function isGsm(string $text): bool
    {
        foreach (mb_str_split($text) as $char) {
            if (! str_contains(self::GSM_BASIC, $char) && ! str_contains(self::GSM_EXTENDED, $char)) {
                return false;
            }
        }

        return true;
    }

    /** Characters as the network counts them. */
    public static function length(string $text): int
    {
        if (! self::isGsm($text)) {
            return (int) (strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')) / 2);
        }
        $length = 0;
        foreach (mb_str_split($text) as $char) {
            $length += str_contains(self::GSM_EXTENDED, $char) ? 2 : 1;
        }

        return $length;
    }

    /** How many SMS pages the text takes (each is charged). */
    public static function segments(string $text): int
    {
        $length = self::length($text);
        [$single, $multi] = self::isGsm($text) ? [160, 153] : [70, 67];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}
