<?php

use App\Services\Accounting\VatDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT treatment (standard, zero-rated, exempt, out of scope) on tax rates
 * and on every invoice, bill, cash sale and credit note line, for the
 * monthly VAT return. See App\Services\Accounting\VatTreatment.
 *
 * Existing data:
 *  - tax rates above 0% are standard; 0% rates named or coded "exempt" or
 *    "zero" get that treatment; other 0% rates are left unset for the
 *    business to choose.
 *  - lines with VAT are standard; 0% lines take their item's zero-rated or
 *    exempt tax rate; the rest are left unset and the return lists them.
 *  - every business gets the "VAT 7.5%", "Zero-rated" and "Exempt" rates.
 */
return new class extends Migration
{
    private array $lineTables = ['invoice_items', 'bill_items', 'sales_receipt_items', 'credit_note_items'];

    public function up(): void
    {
        if (! Schema::hasColumn('tax_rates', 'vat_treatment')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->string('vat_treatment', 20)->nullable()->after('applies_to');
            });
        }
        // Table names written out so static analysis sees the new columns.
        if (! Schema::hasColumn('invoice_items', 'vat_treatment')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->string('vat_treatment', 20)->nullable()->after('tax_amount');
            });
        }
        if (! Schema::hasColumn('bill_items', 'vat_treatment')) {
            Schema::table('bill_items', function (Blueprint $table) {
                $table->string('vat_treatment', 20)->nullable()->after('tax_amount');
            });
        }
        if (! Schema::hasColumn('sales_receipt_items', 'vat_treatment')) {
            Schema::table('sales_receipt_items', function (Blueprint $table) {
                $table->string('vat_treatment', 20)->nullable()->after('tax_amount');
            });
        }
        if (! Schema::hasColumn('credit_note_items', 'vat_treatment')) {
            Schema::table('credit_note_items', function (Blueprint $table) {
                $table->string('vat_treatment', 20)->nullable()->after('tax_amount');
            });
        }

        // Tax rates.
        DB::table('tax_rates')->whereNull('vat_treatment')->where('rate', '>', 0)->update(['vat_treatment' => 'standard']);
        DB::table('tax_rates')->whereNull('vat_treatment')->where('rate', 0)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%exempt%'])->orWhereRaw('LOWER(code) LIKE ?', ['%exempt%']))
            ->update(['vat_treatment' => 'exempt']);
        DB::table('tax_rates')->whereNull('vat_treatment')->where('rate', 0)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%zero%'])->orWhereRaw('LOWER(code) LIKE ?', ['%zero%']))
            ->update(['vat_treatment' => 'zero']);

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            VatDefaults::seedForTenant((int) $tenantId);
        }

        // Lines.
        $itemTreatments = DB::table('items')
            ->join('tax_rates', 'tax_rates.id', '=', 'items.tax_rate_id')
            ->whereIn('tax_rates.vat_treatment', ['zero', 'exempt', 'out_of_scope'])
            ->pluck('tax_rates.vat_treatment', 'items.id');

        foreach ($this->lineTables as $name) {
            DB::table($name)->whereNull('vat_treatment')->where('tax_rate', '>', 0)->update(['vat_treatment' => 'standard']);
            foreach ($itemTreatments->groupBy(fn ($t) => $t, true) as $treatment => $items) {
                foreach ($items->keys()->chunk(500) as $ids) {
                    DB::table($name)->whereNull('vat_treatment')->where('tax_rate', 0)
                        ->whereIn('item_id', $ids->all())
                        ->update(['vat_treatment' => $treatment]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (array_merge(['tax_rates'], $this->lineTables) as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'vat_treatment')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn('vat_treatment');
                });
            }
        }
    }
};
