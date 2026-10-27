<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * "view vat-returns": see the VAT return screens and the "VAT return due"
 * to-do (audit: the Sales role saw them with only "view reports"). Given
 * to admins and to every role that can file VAT returns or see journals,
 * which already show the same VAT figures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'view vat-returns', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->with('permissions')->each(function (Role $role) {
            if (in_array($role->name, ['super-admin', 'admin'], true)
                || $role->permissions->contains('name', 'file vat-returns')
                || $role->permissions->contains('name', 'view journals')) {
                $role->givePermissionTo('view vat-returns');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'view vat-returns')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
