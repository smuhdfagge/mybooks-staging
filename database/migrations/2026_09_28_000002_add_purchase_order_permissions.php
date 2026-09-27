<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Purchase Orders routes require "view/create/edit/delete purchase-orders"
 * but those permissions were never created, so every tenant got 403
 * (finding N2).
 *
 * Each role that can do something with bills gets the same level of access
 * to purchase orders. This includes tenant-customised copies of the system
 * roles, not only the global ones. Also makes sure the bank permissions exist,
 * as they were previously only created by a seeder.
 */
return new class extends Migration
{
    private array $actions = ['view', 'create', 'edit', 'delete'];

    public function up(): void
    {
        foreach ($this->actions as $action) {
            Permission::firstOrCreate(['name' => "{$action} purchase-orders", 'guard_name' => 'web']);
        }

        foreach (['view banks', 'create banks', 'edit banks', 'delete banks', 'reconcile banks'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->with('permissions')->each(function (Role $role) {
            $grant = [];
            foreach ($this->actions as $action) {
                if ($role->permissions->contains('name', "{$action} bills")) {
                    $grant[] = "{$action} purchase-orders";
                }
            }
            if ($role->name === 'super-admin' || $role->name === 'admin') {
                $grant = array_map(fn ($a) => "{$a} purchase-orders", $this->actions);
            }
            if ($grant) {
                $role->givePermissionTo($grant);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->actions as $action) {
            Permission::where('name', "{$action} purchase-orders")->where('guard_name', 'web')->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
