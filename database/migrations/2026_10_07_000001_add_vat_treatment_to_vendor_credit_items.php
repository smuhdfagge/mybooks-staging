<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT treatment on supplier credit lines, like the other document lines
 * (see App\Services\Accounting\VatTreatment), so supplier credits come off
 * the right purchases box of the VAT return.
 *
 * Existing lines: with VAT they are standard; without, they take the
 * treatment of the bill line they credit (same item, else same
 * description), else the item's zero-rated, exempt or out-of-scope tax
 * rate. The rest stay unset ("not classified").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_credit_items')) {
            return;
        }
        if (! Schema::hasColumn('vendor_credit_items', 'vat_treatment')) {
            Schema::table('vendor_credit_items', function (Blueprint $table) {
                $table->string('vat_treatment', 20)->nullable()->after('tax_amount');
            });
        }

        DB::table('vendor_credit_items')->whereNull('vat_treatment')->where('tax_rate', '>', 0)->update(['vat_treatment' => 'standard']);

        // From the bill line credited.
        $lines = DB::table('vendor_credit_items as vci')
            ->join('vendor_credits as vc', 'vc.id', '=', 'vci.vendor_credit_id')
            ->whereNull('vci.vat_treatment')
            ->whereNotNull('vc.bill_id')
            ->get(['vci.id', 'vci.item_id', 'vci.description', 'vc.bill_id']);
        foreach ($lines->groupBy('bill_id') as $billId => $group) {
            $billLines = DB::table('bill_items')->where('bill_id', $billId)
                ->whereIn('vat_treatment', ['zero', 'exempt', 'out_of_scope'])
                ->get(['item_id', 'description', 'vat_treatment']);
            foreach ($group as $line) {
                $match = ($line->item_id ? $billLines->firstWhere('item_id', $line->item_id) : null)
                    ?? $billLines->firstWhere('description', $line->description);
                if ($match) {
                    DB::table('vendor_credit_items')->where('id', $line->id)->update(['vat_treatment' => $match->vat_treatment]);
                }
            }
        }

        // From the item's tax rate.
        $itemTreatments = DB::table('items')
            ->join('tax_rates', 'tax_rates.id', '=', 'items.tax_rate_id')
            ->whereIn('tax_rates.vat_treatment', ['zero', 'exempt', 'out_of_scope'])
            ->pluck('tax_rates.vat_treatment', 'items.id');
        foreach ($itemTreatments->groupBy(fn ($t) => $t, true) as $treatment => $items) {
            foreach ($items->keys()->chunk(500) as $ids) {
                DB::table('vendor_credit_items')->whereNull('vat_treatment')->where('tax_rate', 0)
                    ->whereIn('item_id', $ids->all())
                    ->update(['vat_treatment' => $treatment]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendor_credit_items') && Schema::hasColumn('vendor_credit_items', 'vat_treatment')) {
            Schema::table('vendor_credit_items', function (Blueprint $table) {
                $table->dropColumn('vat_treatment');
            });
        }
    }
};
