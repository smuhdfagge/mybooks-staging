<?php

use App\Services\ChartOfAccountService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration ensures all existing tenants have the default chart of accounts.
     * New tenants will automatically get the default accounts via the Tenant model's
     * booted method.
     */
    public function up(): void
    {
        // Seed default chart of accounts for all existing tenants
        $summary = ChartOfAccountService::seedAllTenants();

        // Log the summary (optional - useful for debugging)
        if ($summary['tenants_seeded'] > 0) {
            \Illuminate\Support\Facades\Log::info('Default Chart of Accounts seeded', $summary);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: We don't remove the accounts on rollback as they may have
     * been used in transactions. Only remove system accounts that have
     * no journal entries.
     */
    public function down(): void
    {
        // We don't remove the accounts on rollback to prevent data loss
        // If needed, accounts can be manually deleted
    }
};
