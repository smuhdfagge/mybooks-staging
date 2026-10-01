<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * "file vat-returns": classify lines on the monthly VAT return, enter its
 * manual lines and mark a month as filed. Given to every role that can post
 * journals (they can already settle VAT), and to admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'file vat-returns', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->with('permissions')->each(function (Role $role) {
            if (in_array($role->name, ['super-admin', 'admin'], true) || $role->permissions->contains('name', 'create journals')) {
                $role->givePermissionTo('file vat-returns');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'file vat-returns')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
