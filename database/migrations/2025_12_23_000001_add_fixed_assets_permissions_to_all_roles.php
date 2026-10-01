<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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

        // Create permissions if they don't exist
        foreach ($fixedAssetPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Assign to super-admin role (all permissions)
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($fixedAssetPermissions);
        }

        // Assign to admin role (all fixed asset permissions)
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($fixedAssetPermissions);
        }

        // Assign limited permissions to accountant role
        $accountantRole = Role::where('name', 'accountant')->first();
        if ($accountantRole) {
            $accountantRole->givePermissionTo([
                'view fixed-assets',
                'create fixed-assets',
                'edit fixed-assets',
                'depreciate fixed-assets',
            ]);
        }

        // Clear permission cache again after assignments
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $fixedAssetPermissions = [
            'view fixed-assets',
            'create fixed-assets',
            'edit fixed-assets',
            'delete fixed-assets',
            'depreciate fixed-assets',
            'dispose fixed-assets',
        ];

        // Remove permissions from roles
        $roles = Role::whereIn('name', ['super-admin', 'admin', 'accountant'])->get();
        foreach ($roles as $role) {
            $role->revokePermissionTo($fixedAssetPermissions);
        }

        // Note: We don't delete the permissions themselves as they may be used by other roles

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
