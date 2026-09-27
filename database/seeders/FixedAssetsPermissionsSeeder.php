<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FixedAssetsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Fixed Assets Permissions
        $fixedAssetPermissions = [
            'view fixed-assets',
            'create fixed-assets',
            'edit fixed-assets',
            'delete fixed-assets',
            'depreciate fixed-assets',
            'dispose fixed-assets',
        ];

        foreach ($fixedAssetPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to super-admin role (all permissions)
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($fixedAssetPermissions);
        }

        // Assign to admin role if exists
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($fixedAssetPermissions);
        }

        // Assign view/create/edit/depreciate to accountant role if exists
        $accountantRole = Role::where('name', 'accountant')->first();
        if ($accountantRole) {
            $accountantRole->givePermissionTo([
                'view fixed-assets',
                'create fixed-assets',
                'edit fixed-assets',
                'depreciate fixed-assets',
            ]);
        }
    }
}
