<?php

namespace App\Support;

/**
 * Writing CSV cells safely (finding S4).
 *
 * Excel and similar programs run a cell as a formula when it starts with
 * = + - or @ (a tab or carriage return can hide the same thing), so a
 * customer called =HYPERLINK(...) became a live link. Such cells get a
 * leading apostrophe, which spreadsheets show as plain text. Plain numbers
 * like -1500.00 are left alone so amounts still add up.
 *
 * The custom report page builds its CSV in the browser with the same rule
 * (resources/views/reports/custom/run.blade.php).
 */
class Csv
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function escapeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || ! in_array($value[0], self::TRIGGERS, true)) {
            return $value;
        }

        if (preg_match('/^[+-]?(\d{1,3}(,\d{3})+|\d*)(\.\d+)?$/', $value) && preg_match('/\d/', $value)) {
            return $value;
        }

        return "'".$value;
    }

    /**
     * Undo escapeCell() when a file we exported is imported again.
     */
    public static function unescapeCell(mixed $value): mixed
    {
        if (is_string($value) && strlen($value) > 1 && $value[0] === "'" && in_array($value[1], self::TRIGGERS, true)) {
            return substr($value, 1);
        }

        return $value;
    }

    /**
     * fputcsv() with every cell escaped.
     *
     * @param  resource  $handle
     * @param  array<int|string, mixed>  $row
     */
    public static function writeRow($handle, array $row): void
    {
        fputcsv($handle, array_map([self::class, 'escapeCell'], array_values($row)), ',', '"', '\\');
    }
}
