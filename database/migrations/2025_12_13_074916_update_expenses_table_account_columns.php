<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Rename account_id to expense_account_id if it exists
            if (Schema::hasColumn('expenses', 'account_id')) {
                $table->renameColumn('account_id', 'expense_account_id');
            } else if (!Schema::hasColumn('expenses', 'expense_account_id')) {
                $table->unsignedBigInteger('expense_account_id')->nullable()->after('vendor_id');
            }
            
            // Add paid_through_id if it doesn't exist
            if (!Schema::hasColumn('expenses', 'paid_through_id')) {
                $table->unsignedBigInteger('paid_through_id')->nullable()->after('expense_account_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'expense_account_id')) {
                $table->renameColumn('expense_account_id', 'account_id');
            }
            if (Schema::hasColumn('expenses', 'paid_through_id')) {
                $table->dropColumn('paid_through_id');
            }
        });
    }
};
