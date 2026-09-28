<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BanksPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Banks Permissions
        $bankPermissions = [
            'view banks',
            'create banks',
            'edit banks',
            'delete banks',
            'reconcile banks',
        ];

        foreach ($bankPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to super-admin role (all permissions)
        $superAdminRole = Role::whereNull('tenant_id')->where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($bankPermissions);
        }

        // Assign to admin role if exists
        $adminRole = Role::whereNull('tenant_id')->where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($bankPermissions);
        }

        // Assign view/create/edit/reconcile to accountant role if exists
        $accountantRole = Role::whereNull('tenant_id')->where('name', 'accountant')->first();
        if ($accountantRole) {
            $accountantRole->givePermissionTo([
                'view banks',
                'create banks',
                'edit banks',
                'reconcile banks',
            ]);
        }
    }
}
