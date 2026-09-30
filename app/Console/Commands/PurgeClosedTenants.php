<?php

namespace App\Console\Commands;

use App\Models\DataRequest;
use App\Models\Tenant;
use App\Services\TenantPurger;
use Illuminate\Console\Command;

/**
 * Erases businesses whose owner closed them at least 30 days ago (O7).
 * Runs daily from the scheduler.
 */
class PurgeClosedTenants extends Command
{
    protected $signature = 'tenants:purge-closed {--dry-run : List the businesses without erasing anything}';

    protected $description = 'Erase the data of businesses closed by their owner once the 30 days have passed';

    public function handle(TenantPurger $purger): int
    {
        $due = Tenant::withTrashed()
            ->whereNotNull('closure_purge_at')
            ->where('closure_purge_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No closed businesses are due for erasure.');

            return self::SUCCESS;
        }

        foreach ($due as $tenant) {
            $label = "#{$tenant->id} {$tenant->name}";

            if ($this->option('dry-run')) {
                $this->line("Would erase {$label}");

                continue;
            }

            $deleted = $purger->purge($tenant);

            DataRequest::where('tenant_id', $tenant->id)
                ->where('type', 'closure')
                ->where('status', DataRequest::STATUS_SCHEDULED)
                ->update([
                    'status' => DataRequest::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'handled_by' => 'tenants:purge-closed',
                    'details' => 'Erased '.array_sum($deleted).' rows across '.count($deleted).' tables.',
                ]);

            $this->info("Erased {$label}: ".array_sum($deleted).' rows.');
        }

        return self::SUCCESS;
    }
}
