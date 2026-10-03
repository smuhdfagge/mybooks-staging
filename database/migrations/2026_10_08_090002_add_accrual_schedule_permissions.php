<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Prepaid expense and deferred revenue schedule permissions (S9). Admins
 * get all four. Roles that can create journals (accountants) get view,
 * create and edit (edit covers "release now" and cancel), plus delete if
 * they can delete journals; roles that can only view journals get view.
 */
return new class extends Migration
{
    private array $names = ['view accrual-schedules', 'create accrual-schedules', 'edit accrual-schedules', 'delete accrual-schedules'];

    public function up(): void
    {
        foreach ($this->names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->with('permissions')->each(function (Role $role) {
            $has = fn (string $name) => $role->permissions->contains('name', $name);
            if (in_array($role->name, ['super-admin', 'admin'], true)) {
                $role->givePermissionTo($this->names);
            } elseif ($has('create journals')) {
                $role->givePermissionTo(['view accrual-schedules', 'create accrual-schedules', 'edit accrual-schedules']);
                if ($has('delete journals')) {
                    $role->givePermissionTo('delete accrual-schedules');
                }
            } elseif ($has('view journals')) {
                $role->givePermissionTo('view accrual-schedules');
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
