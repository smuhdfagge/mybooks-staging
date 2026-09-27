<?php

use App\Services\ChartOfAccountService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds any missing default chart of accounts (including payroll accounts)
     * to all existing tenants using firstOrCreate, so it's safe to re-run.
     */
    public function up(): void
    {
        ChartOfAccountService::seedAllTenants();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible — accounts may already have journal entries.
    }
};
