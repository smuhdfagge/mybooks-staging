<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration updates inventory tables to support fractional quantities
     * (e.g., selling 0.5 units of an item). The decimal(15,4) type allows for
     * precise fractional values up to 4 decimal places.
     */
    public function up(): void
    {
        // SQLite does not support column modification; columns are already flexible
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Update inventories table to support fractional quantities
        Schema::table('inventories', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->default(0)->change();
            $table->decimal('reserved_quantity', 15, 4)->default(0)->change();
        });

        // Update inventory_histories table to support fractional quantities
        Schema::table('inventory_histories', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Revert inventories table back to integer
        Schema::table('inventories', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
            $table->integer('reserved_quantity')->default(0)->change();
        });

        // Revert inventory_histories table back to integer
        Schema::table('inventory_histories', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });
    }
};
