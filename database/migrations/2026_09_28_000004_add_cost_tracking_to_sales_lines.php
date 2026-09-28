<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost of goods sold is fixed once per sales line (findings M3, N6).
 *
 * - invoice_items.unit_cost / sales_receipt_items.unit_cost: the cost per
 *   unit decided when the document was posted. The journal reads it rather
 *   than recalculating from today's prices.
 * - inventory_layer_consumptions: which cost layers each document used, so
 *   editing, cancelling or deleting it can put exactly that stock back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 4)->nullable()->after('unit_price');
        });

        Schema::table('sales_receipt_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 4)->nullable()->after('unit_price');
        });

        Schema::create('inventory_layer_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            // Null when stock was issued beyond the recorded layers (costed at the fallback price)
            $table->foreignId('inventory_layer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            // Whether this issue also reduced quantity on hand (cash sales do; invoices reduce it on release)
            $table->boolean('reduced_on_hand')->default(false);
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'layer_consumptions_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_layer_consumptions');

        Schema::table('sales_receipt_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
