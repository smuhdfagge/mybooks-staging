<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include reserved and unreserved types
        // SQLite does not support ALTER TABLE MODIFY COLUMN, but treats columns as flexible types
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_histories MODIFY COLUMN type ENUM('in', 'out', 'adjustment', 'transfer', 'reserved', 'unreserved') DEFAULT 'in'");
        }
        // SQLite: enum values are not enforced at the DB level, so nothing to change
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_histories MODIFY COLUMN type ENUM('in', 'out', 'adjustment', 'transfer') DEFAULT 'in'");
        }
    }
};
