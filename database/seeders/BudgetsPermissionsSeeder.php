<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BudgetsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Budget Permissions
        $budgetPermissions = [
            'view budgets',
            'create budgets',
            'edit budgets',
            'delete budgets',
        ];

        foreach ($budgetPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to super-admin role (all permissions)
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($budgetPermissions);
        }

        // Assign to admin role if exists
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($budgetPermissions);
        }

        // Assign view/create/edit to accountant role if exists
        $accountantRole = Role::where('name', 'accountant')->first();
        if ($accountantRole) {
            $accountantRole->givePermissionTo([
                'view budgets',
                'create budgets',
                'edit budgets',
            ]);
        }

        // Assign view only to viewer role if exists
        $viewerRole = Role::where('name', 'viewer')->first();
        if ($viewerRole) {
            $viewerRole->givePermissionTo([
                'view budgets',
            ]);
        }
    }
}
