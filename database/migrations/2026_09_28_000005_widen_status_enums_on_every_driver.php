<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two earlier migrations added enum values only when the driver name was
 * exactly "mysql", so on SQLite (tests, local) and on a "mariadb" connection
 * the old values were still enforced:
 *
 *  - inventory_histories.type had no 'reserved' / 'unreserved', so reserving
 *    stock for an invoice failed with a CHECK constraint error;
 *  - sales_orders.status had no 'invoiced'.
 *
 * Schema::change() works on every driver, so this sets the full lists again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_histories', function (Blueprint $table) {
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer', 'reserved', 'unreserved'])
                ->default('in')->change();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->enum('status', ['draft', 'confirmed', 'processing', 'invoiced', 'completed', 'cancelled'])
                ->default('draft')->change();
        });
    }

    public function down(): void
    {
        // Leave the wider lists in place: narrowing them would fail on rows
        // that already use the new values.
    }
};
