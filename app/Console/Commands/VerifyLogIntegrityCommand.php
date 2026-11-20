<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\LogIntegrityService;
use Illuminate\Console\Command;

/**
 * Verify the HMAC integrity chain of activity logs.
 *
 * Usage:
 *   php artisan logs:verify              # verify all tenants
 *   php artisan logs:verify --tenant=5   # verify a specific tenant
 */
class VerifyLogIntegrityCommand extends Command
{
    protected $signature = 'logs:verify
                            {--tenant= : Verify a specific tenant ID}
                            {--limit=5000 : Check the newest N entries per tenant (0: all)}';

    protected $description = 'Verify HMAC integrity of activity log entries';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $result = $this->verifyTenant((int) $tenantId, $limit);
            // A deleted entry shows on the entry after it in the whole log,
            // which can be another business's.
            $this->line('  Note: a deleted entry is reported on the entry after it, which may be another business\'s. Run without --tenant to check every link.');

            return $result;
        }

        $tenants = Tenant::all();
        $allValid = true;

        foreach ($tenants as $tenant) {
            $result = $this->verifyTenant($tenant->id, $limit);
            if ($result !== self::SUCCESS) {
                $allValid = false;
            }
        }

        return $allValid ? self::SUCCESS : self::FAILURE;
    }

    protected function verifyTenant(int $tenantId, int $limit): int
    {
        $this->info("Verifying tenant #{$tenantId}...");

        $result = Tenant::run($tenantId, fn () => LogIntegrityService::verifyChain($tenantId, $limit));

        if ($result['valid']) {
            $this->info("  ✓ {$result['checked']} entries verified — no tampering detected.");

            return self::SUCCESS;
        }

        $this->error('  ✗ Integrity violations found:');
        foreach ($result['errors'] as $error) {
            $this->line("    - {$error}");
        }

        return self::FAILURE;
    }
}
