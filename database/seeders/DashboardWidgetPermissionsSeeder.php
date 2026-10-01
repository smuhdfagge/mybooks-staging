<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DashboardWidgetPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $widgetPermissions = [
            'total-revenue dashboard-widgets',
            'outstanding-receivables dashboard-widgets',
            'monthly-expenses dashboard-widgets',
            'employees-count dashboard-widgets',
            'quick-actions dashboard-widgets',
            'revenue-chart dashboard-widgets',
            'recent-invoices dashboard-widgets',
            'pending-bills dashboard-widgets',
            'low-stock dashboard-widgets',
        ];

        foreach ($widgetPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Super Admin - all widgets
        $superAdmin = Role::whereNull('tenant_id')->where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($widgetPermissions);
        }

        // Admin - all widgets
        $admin = Role::whereNull('tenant_id')->where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo($widgetPermissions);
        }

        // Accountant - financial widgets
        $accountant = Role::whereNull('tenant_id')->where('name', 'accountant')->first();
        if ($accountant) {
            $accountant->givePermissionTo([
                'total-revenue dashboard-widgets',
                'outstanding-receivables dashboard-widgets',
                'monthly-expenses dashboard-widgets',
                'revenue-chart dashboard-widgets',
                'recent-invoices dashboard-widgets',
                'pending-bills dashboard-widgets',
                'low-stock dashboard-widgets',
            ]);
        }

        // Sales - sales-related widgets
        $sales = Role::whereNull('tenant_id')->where('name', 'sales')->first();
        if ($sales) {
            $sales->givePermissionTo([
                'total-revenue dashboard-widgets',
                'outstanding-receivables dashboard-widgets',
                'quick-actions dashboard-widgets',
                'recent-invoices dashboard-widgets',
            ]);
        }

        // HR Manager - employee widget
        $hr = Role::whereNull('tenant_id')->where('name', 'hr-manager')->first();
        if ($hr) {
            $hr->givePermissionTo([
                'employees-count dashboard-widgets',
                'quick-actions dashboard-widgets',
            ]);
        }

        // Viewer - read-only overview widgets
        $viewer = Role::whereNull('tenant_id')->where('name', 'viewer')->first();
        if ($viewer) {
            $viewer->givePermissionTo([
                'total-revenue dashboard-widgets',
                'outstanding-receivables dashboard-widgets',
                'monthly-expenses dashboard-widgets',
                'employees-count dashboard-widgets',
                'revenue-chart dashboard-widgets',
                'recent-invoices dashboard-widgets',
                'pending-bills dashboard-widgets',
                'low-stock dashboard-widgets',
            ]);
        }

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
