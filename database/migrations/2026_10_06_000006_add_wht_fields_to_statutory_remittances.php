<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paying WHT to the tax authority is a statutory remittance (body "wht").
 * It also records which authority it went to (the NRS, or a state IRS for
 * WHT deducted from individuals) and the bank account it was paid from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statutory_remittances', function (Blueprint $table) {
            if (! Schema::hasColumn('statutory_remittances', 'bank_id')) {
                $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            }
            if (! Schema::hasColumn('statutory_remittances', 'wht_authority')) {
                $table->string('wht_authority', 20)->nullable();
            }
            if (! Schema::hasColumn('statutory_remittances', 'wht_state')) {
                $table->string('wht_state', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('statutory_remittances', function (Blueprint $table) {
            if (Schema::hasColumn('statutory_remittances', 'bank_id')) {
                $table->dropConstrainedForeignId('bank_id');
            }
            foreach (['wht_authority', 'wht_state'] as $column) {
                if (Schema::hasColumn('statutory_remittances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
