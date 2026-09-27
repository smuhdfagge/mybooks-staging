<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ChartOfAccountService;
use Illuminate\Console\Command;

class SeedDefaultChartOfAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:seed-accounts 
                            {--tenant= : Specific tenant ID to seed (optional)}
                            {--force : Force seeding even if accounts exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed default chart of accounts for all tenants or a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $force = $this->option('force');

        if ($tenantId) {
            return $this->seedSingleTenant($tenantId, $force);
        }

        return $this->seedAllTenants();
    }

    /**
     * Seed accounts for a single tenant
     */
    private function seedSingleTenant(int $tenantId, bool $force): int
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant with ID {$tenantId} not found.");
            return Command::FAILURE;
        }

        $hasAccounts = ChartOfAccountService::tenantHasAccounts($tenantId);

        if ($hasAccounts && !$force) {
            $this->warn("Tenant '{$tenant->name}' already has chart of accounts.");
            $this->info("Use --force to add any missing default accounts.");
            return Command::SUCCESS;
        }

        $accountsCreated = ChartOfAccountService::createDefaultAccounts($tenantId);

        if ($accountsCreated > 0) {
            $this->info("Created {$accountsCreated} default accounts for tenant '{$tenant->name}'.");
        } else {
            $this->info("No new accounts needed for tenant '{$tenant->name}'. All default accounts already exist.");
        }

        return Command::SUCCESS;
    }

    /**
     * Seed accounts for all tenants
     */
    private function seedAllTenants(): int
    {
        $this->info('Seeding default chart of accounts for all tenants...');
        $this->newLine();

        $summary = ChartOfAccountService::seedAllTenants();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Tenants', $summary['total_tenants']],
                ['Tenants Updated', $summary['tenants_seeded']],
                ['Tenants Skipped (No Changes)', $summary['tenants_skipped']],
                ['Total Accounts Created', $summary['total_accounts_created']],
            ]
        );

        $this->newLine();
        $this->info('Default chart of accounts seeding completed!');

        return Command::SUCCESS;
    }
}
