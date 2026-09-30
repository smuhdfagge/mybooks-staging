<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Date-part SQL expressions that work on SQLite and MySQL/MariaDB,
 * so reports can group by month in one query instead of one query
 * per month (P2, P5).
 */
class SqlDate
{
    /** "YYYY-MM" for the given column. */
    public static function month(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /** "YYYY-MM-DD" for the given column. */
    public static function day(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    /** Day of week as MySQL's DAYOFWEEK(): 1 = Sunday ... 7 = Saturday. */
    public static function dayOfWeek(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "(CAST(strftime('%w', {$column}) AS INTEGER) + 1)",
            'pgsql' => "(EXTRACT(DOW FROM {$column})::int + 1)",
            default => "DAYOFWEEK({$column})",
        };
    }

    /** Hour of day, 0-23. */
    public static function hour(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            'pgsql' => "EXTRACT(HOUR FROM {$column})::int",
            default => "HOUR({$column})",
        };
    }
}
