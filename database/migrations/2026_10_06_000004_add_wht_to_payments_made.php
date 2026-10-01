<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax deducted when paying a vendor. `amount` stays the money
 * paid out of the bank (the net); wht_amount is what was withheld and is
 * owed to the tax authority (wht_authority: nrs or state, with the state
 * for individuals). Net plus WHT settles the bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments_made', 'wht_amount')) {
            Schema::table('payments_made', function (Blueprint $table) {
                $table->foreignId('wht_category_id')->nullable()->constrained('wht_categories')->nullOnDelete();
                $table->decimal('wht_rate', 5, 2)->default(0);
                $table->decimal('wht_base', 15, 2)->default(0);
                $table->decimal('wht_amount', 15, 2)->default(0);
                $table->string('wht_authority', 20)->nullable();
                $table->string('wht_state', 100)->nullable();
                $table->index(['tenant_id', 'wht_authority', 'payment_date'], 'payments_made_wht_schedule_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments_made', 'wht_amount')) {
            Schema::table('payments_made', function (Blueprint $table) {
                $table->dropIndex('payments_made_wht_schedule_index');
                $table->dropConstrainedForeignId('wht_category_id');
                $table->dropColumn(['wht_rate', 'wht_base', 'wht_amount', 'wht_authority', 'wht_state']);
            });
        }
    }
};
