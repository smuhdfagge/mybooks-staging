<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finding N8: a bill made from a purchase order remembers the order, and
 * the order can be marked "billed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('status', ['draft', 'confirmed', 'partially_received', 'received', 'billed', 'cancelled'])
                ->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};
