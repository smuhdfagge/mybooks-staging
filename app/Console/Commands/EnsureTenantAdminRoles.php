<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureTenantAdminRoles extends Command
{
    protected $signature = 'mybooks:ensure-admin-roles {--tenant= : Fix a specific tenant ID} {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Ensure the first user of each tenant has the admin role assigned';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        // Ensure the global admin role exists
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['tenant_id' => null]
        );

        $this->info("Admin role ID: {$adminRole->id}");

        // Find the first user per tenant (the registering admin)
        $query = User::query()
            ->whereNotNull('tenant_id')
            ->where('is_active', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Get first user per tenant (lowest ID = the one who registered)
        $firstUsers = $query
            ->select('users.*')
            ->whereIn('users.id', function ($sub) use ($tenantId) {
                $sub->select(DB::raw('MIN(id)'))
                    ->from('users')
                    ->whereNotNull('tenant_id')
                    ->where('is_active', true)
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->groupBy('tenant_id');
            })
            ->with('roles')
            ->get();

        $fixed = 0;
        $alreadyOk = 0;

        foreach ($firstUsers as $user) {
            if ($user->hasRole('admin')) {
                $alreadyOk++;
                continue;
            }

            if ($dryRun) {
                $this->warn("Would assign admin role to: {$user->name} ({$user->email}) - Tenant #{$user->tenant_id}");
            } else {
                $user->assignRole($adminRole);
                $this->info("Assigned admin role to: {$user->name} ({$user->email}) - Tenant #{$user->tenant_id}");
            }
            $fixed++;
        }

        $this->newLine();
        $this->info("Already OK: {$alreadyOk}");
        $this->info(($dryRun ? 'Would fix' : 'Fixed') . ": {$fixed}");

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->comment('Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }
}
