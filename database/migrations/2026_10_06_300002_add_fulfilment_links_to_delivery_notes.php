<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery notes against sales orders:
 *  - each delivery note line points at the order line it delivers, so
 *    partial deliveries add up per line (matching on the item alone went
 *    wrong when an order had the same item twice or a line without an item);
 *  - the note remembers when it was dispatched;
 *  - order lines count what has been invoiced separately from what has
 *    been delivered. Converting an order to an invoice used to invoice
 *    "ordered minus delivered", so goods delivered first were never billed.
 *    Orders that already have an invoice are counted as fully invoiced,
 *    which is what the old one-invoice-per-order conversion did.
 * Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_note_items', 'sales_order_item_id')) {
            Schema::table('delivery_note_items', function (Blueprint $table) {
                $table->foreignId('sales_order_item_id')->nullable()->after('delivery_note_id')
                    ->constrained('sales_order_items')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('delivery_notes', 'dispatched_at')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->timestamp('dispatched_at')->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('sales_order_items', 'quantity_invoiced')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->decimal('quantity_invoiced', 15, 2)->default(0)->after('quantity_fulfilled');
            });

            $invoicedOrders = DB::table('invoices')->whereNotNull('sales_order_id')->whereNull('deleted_at')
                ->distinct()->pluck('sales_order_id');
            foreach ($invoicedOrders->chunk(500) as $ids) {
                DB::table('sales_order_items')->whereIn('sales_order_id', $ids->all())
                    ->update(['quantity_invoiced' => DB::raw('quantity')]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_item_id');
        });
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('dispatched_at');
        });
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('quantity_invoiced');
        });
    }
};
