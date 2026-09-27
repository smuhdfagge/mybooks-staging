<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($widgetPermissions);
        }

        // Admin - all widgets
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo($widgetPermissions);
        }

        // Accountant - financial widgets
        $accountant = Role::where('name', 'accountant')->first();
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
        $sales = Role::where('name', 'sales')->first();
        if ($sales) {
            $sales->givePermissionTo([
                'total-revenue dashboard-widgets',
                'outstanding-receivables dashboard-widgets',
                'quick-actions dashboard-widgets',
                'recent-invoices dashboard-widgets',
            ]);
        }

        // HR Manager - employee widget
        $hr = Role::where('name', 'hr-manager')->first();
        if ($hr) {
            $hr->givePermissionTo([
                'employees-count dashboard-widgets',
                'quick-actions dashboard-widgets',
            ]);
        }

        // Viewer - read-only overview widgets
        $viewer = Role::where('name', 'viewer')->first();
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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
