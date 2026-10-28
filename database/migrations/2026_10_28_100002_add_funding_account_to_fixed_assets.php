<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For an asset bought on a vendor bill: the account that bill posted the
 * cost to. Bills used to post every line to Miscellaneous Expense, so the
 * asset's purchase journal always took the cost back off that account;
 * bill lines now post to their own account (audit MB-14), so the asset
 * says which one. Empty keeps the old behaviour (Miscellaneous Expense).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fixed_assets', 'funding_account_id')) {
            return;
        }
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->foreignId('funding_account_id')->nullable()->after('funding_source')
                ->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fixed_assets', 'funding_account_id')) {
            return;
        }
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('funding_account_id');
        });
    }
};
