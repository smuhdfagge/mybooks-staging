<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MySQL/MariaDB only: replace "zero" dates ('0000-00-00', '0000-00-00
 * 00:00:00') left by old imports or a server that once ran without strict
 * mode.
 *
 * With strict mode on (Laravel's default), any change that rebuilds such a
 * table - adding a foreign key, for example - fails with "Incorrect datetime
 * value: '0000-00-00 00:00:00'". This runs before the April 2026 inventory
 * migration, which adds foreign keys to `items`.
 *
 * - created_at / updated_at: set to now (the real date is unknown)
 * - other columns that allow NULL: set to NULL
 * - other columns that don't: set to 1970-01-01, and listed in the output
 *   so someone can correct them
 *
 * Safe to run on any database: when there are no zero dates it changes
 * nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $database = DB::getDatabaseName();
        $columns = DB::select(
            "SELECT c.TABLE_NAME AS t, c.COLUMN_NAME AS c, c.DATA_TYPE AS type, c.IS_NULLABLE AS nullable
               FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = ? AND t.TABLE_TYPE = 'BASE TABLE'
                AND c.DATA_TYPE IN ('date', 'datetime', 'timestamp')",
            [$database]
        );

        // Zero dates can only be read and fixed with strict mode off; put the
        // connection's own mode back afterwards.
        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            foreach ($columns as $col) {
                $table = str_replace('`', '', $col->t);
                $column = str_replace('`', '', $col->c);
                $zero = "(`{$column}` < '1000-01-01' OR MONTH(`{$column}`) = 0 OR DAYOFMONTH(`{$column}`) = 0)";

                // Table names come from information_schema, so they already
                // carry any table prefix: use raw SQL, not DB::table().
                $count = (int) DB::selectOne("SELECT COUNT(*) AS n FROM `{$table}` WHERE {$zero}")->n;
                if ($count === 0) {
                    continue;
                }

                if (in_array($column, ['created_at', 'updated_at'], true)) {
                    $value = 'NOW()';
                } elseif ($col->nullable === 'YES') {
                    $value = 'NULL';
                } else {
                    $value = $col->type === 'date' ? "'1970-01-01'" : "'1970-01-01 00:00:00'";
                    echo "  Zero dates in {$table}.{$column} ({$count} rows) set to 1970-01-01; please check them.\n";
                }

                DB::update("UPDATE `{$table}` SET `{$column}` = {$value} WHERE {$zero}");
            }
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};
