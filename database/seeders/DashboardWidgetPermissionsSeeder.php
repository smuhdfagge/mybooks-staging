<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dashboard card permissions for the built-in roles. The cards, their
 * names and descriptions are listed in config/dashboard.php.
 */
class DashboardWidgetPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $all = array_map(fn ($key) => "{$key} dashboard-widgets", array_keys(config('dashboard.widgets')));

        foreach ($all as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $w = fn (array $keys) => array_map(fn ($key) => "{$key} dashboard-widgets", $keys);

        $byRole = [
            'super-admin' => $all,
            'admin' => $all,
            // Profit and the bank balance: Admin and Accountant only.
            'accountant' => $w(['attention-list', 'cash-position', 'total-revenue', 'monthly-expenses', 'profit', 'revenue-chart',
                'outstanding-receivables', 'pending-bills', 'top-customers', 'expense-breakdown', 'recent-invoices', 'low-stock']),
            'sales' => $w(['attention-list', 'total-revenue', 'outstanding-receivables', 'top-customers', 'quick-actions', 'recent-invoices']),
            'hr-manager' => $w(['attention-list', 'quick-actions']),
            'viewer' => $w(['attention-list', 'total-revenue', 'outstanding-receivables', 'monthly-expenses', 'revenue-chart',
                'top-customers', 'expense-breakdown', 'recent-invoices', 'pending-bills', 'low-stock']),
        ];

        foreach ($byRole as $name => $permissions) {
            Role::whereNull('tenant_id')->where('name', $name)->first()?->givePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
