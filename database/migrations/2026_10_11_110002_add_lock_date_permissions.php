<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lock date permissions (session 11). "manage lock-dates" sets, moves and
 * clears the lock dates: admins only. "override lock-date" lets a user
 * change things dated on or before the staff lock date (never the
 * all-users one): admins and the accountant (adviser) role.
 */
return new class extends Migration
{
    private array $names = ['manage lock-dates', 'override lock-date'];

    public function up(): void
    {
        foreach ($this->names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->each(function (Role $role) {
            if (in_array($role->name, ['super-admin', 'admin'], true)) {
                $role->givePermissionTo($this->names);
            } elseif ($role->name === 'accountant') {
                $role->givePermissionTo('override lock-date');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->names)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
