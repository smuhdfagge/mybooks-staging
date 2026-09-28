<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeRetentionData extends Command
{
    protected $signature = 'retention:purge
                            {--dry-run : Preview what would be purged without making changes}
                            {--tenant= : Target a specific tenant ID}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Anonymize terminated employee PII and prune old records per retention policy';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $config = config('mybooks.retention');
        $terminatedMonths = $config['terminated_employee_months'] ?? 84;
        $activityLogMonths = $config['activity_log_months'] ?? 84;
        $softDeletedMonths = $config['soft_deleted_months'] ?? 12;

        if ($dryRun) {
            $this->info('[DRY RUN] No changes will be made.');
        }

        $this->info('Retention policy:');
        $this->info("  Terminated employees PII anonymized after: {$terminatedMonths} months");
        $this->info("  Activity logs pruned after: {$activityLogMonths} months");
        $this->info("  Soft-deleted records hard-deleted after: {$softDeletedMonths} months");
        $this->newLine();

        $anonymized = $this->anonymizeTerminatedEmployees($terminatedMonths, $tenantId, $dryRun);
        $pruned = $this->pruneActivityLogs($activityLogMonths, $tenantId, $dryRun);
        $hardDeleted = $this->purgeSoftDeletedEmployees($softDeletedMonths, $tenantId, $dryRun);

        $this->newLine();
        $this->table(
            ['Action', 'Count'],
            [
                ['Employees PII anonymized', $anonymized],
                ['Activity log entries pruned', $pruned],
                ['Soft-deleted employees hard-deleted', $hardDeleted],
            ]
        );

        if ($dryRun) {
            $this->warn('No changes were made. Remove --dry-run to execute.');
        }

        return self::SUCCESS;
    }

    protected function anonymizeTerminatedEmployees(int $months, ?string $tenantId, bool $dryRun): int
    {
        if ($months <= 0) {
            $this->info('Employee anonymization disabled (0 months).');

            return 0;
        }

        $cutoff = Carbon::now()->subMonths($months);

        $query = Employee::withoutGlobalScopes()
            ->whereIn('status', ['terminated', 'resigned'])
            ->whereNotNull('termination_date')
            ->where('termination_date', '<=', $cutoff)
            ->where('first_name', '!=', 'ANONYMIZED'); // Skip already anonymized

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $employees = $query->get();
        $count = $employees->count();

        if ($count === 0) {
            $this->info('No terminated employees past retention period.');

            return 0;
        }

        $this->info("Found {$count} terminated employee(s) past {$months}-month retention.");

        if ($dryRun) {
            $employees->each(function ($emp) {
                $this->line("  Would anonymize: {$emp->employee_id} (terminated {$emp->termination_date->format('Y-m-d')})");
            });

            return $count;
        }

        if (! $this->option('force') && ! $this->confirm("Anonymize PII for {$count} employee(s)?")) {
            $this->info('Skipped employee anonymization.');

            return 0;
        }

        $anonymized = 0;

        foreach ($employees as $employee) {
            DB::transaction(function () use ($employee) {
                // Log the anonymization event before wiping data
                ActivityLog::create([
                    'tenant_id' => $employee->tenant_id,
                    'user_id' => null,
                    'user_name' => 'System (Retention)',
                    'action' => 'anonymized',
                    'model_type' => Employee::class,
                    'model_id' => $employee->id,
                    'model_name' => $employee->employee_id,
                    'description' => "Employee '{$employee->employee_id}' PII anonymized per retention policy ({$employee->termination_date->format('Y-m-d')} termination).",
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'retention:purge',
                ]);

                // Anonymize PII fields — keep employee_id and financial summary for audit
                $employee->forceFill([
                    'first_name' => 'ANONYMIZED',
                    'last_name' => 'EMPLOYEE',
                    'email' => "anon-{$employee->id}@redacted.local",
                    'phone' => null,
                    'date_of_birth' => null,
                    'address' => null,
                    'city' => null,
                    'state' => null,
                    'country' => null,
                    'postal_code' => null,
                    'bank_name' => null,
                    'bank_account_number' => null,
                    'bank_routing_number' => null,
                    'tax_id' => null,
                    'emergency_contact_name' => null,
                    'emergency_contact_phone' => null,
                    'notes' => null,
                ]);

                // Save without triggering LogsActivity for the anonymization itself
                $employee->saveQuietly();
            });

            $anonymized++;
            $this->line("  Anonymized: {$employee->employee_id}");
        }

        return $anonymized;
    }

    protected function pruneActivityLogs(int $months, ?string $tenantId, bool $dryRun): int
    {
        if ($months <= 0) {
            $this->info('Activity log pruning disabled (0 months).');

            return 0;
        }

        $cutoff = Carbon::now()->subMonths($months);

        $query = ActivityLog::where('created_at', '<=', $cutoff);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No activity logs past retention period.');

            return 0;
        }

        $this->info("Found {$count} activity log(s) older than {$months} months.");

        if ($dryRun) {
            return $count;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} old activity log(s)?")) {
            $this->info('Skipped activity log pruning.');

            return 0;
        }

        // Delete in batches to avoid memory issues
        $deleted = 0;
        $batchQuery = ActivityLog::where('created_at', '<=', $cutoff);
        if ($tenantId) {
            $batchQuery->where('tenant_id', $tenantId);
        }

        do {
            $batch = $batchQuery->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }

    protected function purgeSoftDeletedEmployees(int $months, ?string $tenantId, bool $dryRun): int
    {
        if ($months <= 0) {
            $this->info('Soft-delete purging disabled (0 months).');

            return 0;
        }

        $cutoff = Carbon::now()->subMonths($months);

        $query = Employee::withoutGlobalScopes()
            ->onlyTrashed()
            ->where('deleted_at', '<=', $cutoff);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No soft-deleted employees past purge period.');

            return 0;
        }

        $this->info("Found {$count} soft-deleted employee(s) older than {$months} months.");

        if ($dryRun) {
            return $count;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently delete {$count} soft-deleted employee(s)?")) {
            $this->info('Skipped soft-delete purging.');

            return 0;
        }

        $deleted = 0;
        $query->chunk(100, function ($employees) use (&$deleted) {
            foreach ($employees as $employee) {
                $employee->forceDelete();
                $deleted++;
            }
        });

        return $deleted;
    }
}
