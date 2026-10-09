<?php

namespace App\Support\Rebrand;

/**
 * The colour rules for the MyBooks rebrand (R1), shared by the
 * rebrand:colours command and the guard test so both agree on what an
 * "old colour" is.
 *
 * Plan: docs/REBRAND-PLAN.md, section "Replacement rules".
 */
class ColourRules
{
    /** Tailwind colour families the rebrand removes. */
    public const OLD_FAMILIES = ['indigo', 'blue', 'purple', 'violet', 'fuchsia', 'pink', 'rose', 'sky', 'cyan'];

    /** Families swapped to brand without asking. */
    public const AUTO_FAMILIES = ['indigo', 'blue'];

    /** Swapped to brand unless the same file also uses indigo or blue. */
    public const MAYBE_FAMILIES = ['purple', 'violet'];

    /** Old hex colours with a fixed new value (lower case). */
    public const HEX_MAP = [
        '#4f46e5' => '#1F4E79', // indigo-600
        '#3b82f6' => '#1F4E79', // blue-500
        '#2563eb' => '#1F4E79', // blue-600
        '#6366f1' => '#3A6798', // indigo-500
        '#1d4ed8' => '#183E61', // blue-700
        '#4338ca' => '#183E61', // indigo-700
        '#dbeafe' => '#D9E4EF', // blue-100
        '#e0e7ff' => '#D9E4EF', // indigo-100
        '#eef2ff' => '#EEF3F8', // indigo-50
        '#eff6ff' => '#EEF3F8', // blue-50
    ];

    /** Old hex colours that need a person to decide (gradients, purples). */
    public const HEX_FLAG = [
        '#667eea', '#764ba2', '#7c3aed', '#8b5cf6', '#a855f7', '#9333ea', '#6d28d9',
        '#4c1d95', '#312e81', '#1e1b4b', '#818cf8', '#a5b4fc', '#c7d2fe', '#60a5fa',
        '#93c5fd', '#1e40af', '#1e3a8a', '#3730a3', '#ec4899', '#db2777', '#06b6d4',
    ];

    private const SHADES = '(?:50|100|200|300|400|500|600|700|800|900|950)';

    /** Any Tailwind class in an old colour family, e.g. "hover:bg-indigo-600/50". */
    public static function oldClassPattern(): string
    {
        return '/\b(?:'.implode('|', self::OLD_FAMILIES).')-'.self::SHADES.'\b/';
    }

    /** A gradient: bg-gradient-* or a from-/via-/to- colour stop. */
    public static function gradientPattern(): string
    {
        return '/\bbg-gradient-to-[a-z]+\b|(?<![\w-])(?:[a-z-]+:)*(?:from|via|to)-[a-z]+-'.self::SHADES.'\b|linear-gradient\(/';
    }

    /** Old hex colours (fixed ones and flagged ones). */
    public static function oldHexPattern(): string
    {
        $all = array_merge(array_keys(self::HEX_MAP), self::HEX_FLAG);

        return '/(?:'.implode('|', array_map(fn ($h) => preg_quote($h, '/'), $all)).')\b/i';
    }

    /** True when a line still holds a colour the rebrand removes. */
    public static function lineHasOldColour(string $line): bool
    {
        return preg_match(self::oldClassPattern(), $line) === 1
            || preg_match(self::gradientPattern(), $line) === 1
            || preg_match(self::oldHexPattern(), $line) === 1;
    }

    /**
     * Apply the automatic rules to one file's text.
     *
     * @return array{text: string, changes: int, flags: list<array{line: int, reason: string, text: string}>}
     */
    public static function apply(string $text): array
    {
        $usesIndigoOrBlue = preg_match('/\b(?:indigo|blue)-'.self::SHADES.'\b/', $text) === 1;
        $lines = preg_split('/(?<=\n)/', $text) ?: [];
        $changes = 0;
        $flags = [];

        foreach ($lines as $i => $line) {
            $n = $i + 1;

            // Gradients are always a person's call (flat brand-600, or brand-900
            // for a big dark header). Leave the whole line alone.
            if (preg_match(self::gradientPattern(), $line)) {
                $flags[] = ['line' => $n, 'reason' => 'gradient: use flat brand-600 (or brand-900 for a big dark header)', 'text' => trim($line)];

                continue;
            }

            $families = self::AUTO_FAMILIES;
            if (! $usesIndigoOrBlue) {
                $families = array_merge($families, self::MAYBE_FAMILIES);
            }

            $new = preg_replace_callback(
                '/(?<![\w-])((?:[a-z0-9-]+:)*)(-?[a-z]+(?:-[a-z]+)*)-('.implode('|', $families).')-('.self::SHADES.')((?:\/\d+)?)\b/',
                function (array $m) use (&$changes) {
                    [$all, $variants, $utility, , $shade, $opacity] = $m;
                    $changes++;
                    // Text in dark mode: shades 400-600 are too dark on grey-800,
                    // so they move up to brand-300 (6.0 to 1 contrast).
                    if (str_contains($variants, 'dark:') && in_array($utility, ['text', 'placeholder'], true) && in_array($shade, ['400', '500', '600'], true)) {
                        $shade = '300';
                    }

                    return $variants.$utility.'-brand-'.$shade.$opacity;
                },
                $line
            ) ?? $line;

            $new = preg_replace_callback('/#[0-9a-fA-F]{6}\b/', function (array $m) use (&$changes) {
                $hex = strtolower($m[0]);
                if (isset(self::HEX_MAP[$hex])) {
                    $changes++;

                    return self::HEX_MAP[$hex];
                }

                return $m[0];
            }, $new) ?? $new;

            if (preg_match(self::oldClassPattern(), $new, $m)) {
                $why = in_array(explode('-', $m[0])[0], self::MAYBE_FAMILIES, true)
                    ? 'purple or violet next to blue on this page: pick accent (ochre) or grey so the two stay different'
                    : 'colour with no fixed rule ('.$m[0].'): pick brand, accent, red or grey';
                $flags[] = ['line' => $n, 'reason' => $why, 'text' => trim($new)];
            } elseif (preg_match(self::oldHexPattern(), $new, $m)) {
                $flags[] = ['line' => $n, 'reason' => 'old hex colour '.$m[0].': pick a value from config/brand.php', 'text' => trim($new)];
            } elseif (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $new, $m) && max($m[1], $m[2], $m[3]) - min($m[1], $m[2], $m[3]) > 24) {
                $flags[] = ['line' => $n, 'reason' => 'rgb() colour: check it against config/brand.php', 'text' => trim($new)];
            }

            $lines[$i] = $new;
        }

        return ['text' => implode('', $lines), 'changes' => $changes, 'flags' => $flags];
    }
}
