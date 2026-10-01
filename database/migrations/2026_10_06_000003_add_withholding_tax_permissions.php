<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Withholding tax permissions:
 *  - view withholding-tax: rates, the monthly WHT schedule, the WHT
 *    receivable report;
 *  - manage withholding-tax: edit rates and settings, record WHT credit
 *    notes received and use them against income tax;
 *  - remit withholding-tax: record paying WHT to the tax authority.
 *
 * Admins get all three. Roles that can post journals (accountants) get all
 * three; roles that can only view journals get view.
 */
return new class extends Migration
{
    private array $names = ['view withholding-tax', 'manage withholding-tax', 'remit withholding-tax'];

    public function up(): void
    {
        foreach ($this->names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->with('permissions')->each(function (Role $role) {
            if (in_array($role->name, ['super-admin', 'admin'], true) || $role->permissions->contains('name', 'create journals')) {
                $role->givePermissionTo($this->names);
            } elseif ($role->permissions->contains('name', 'view journals')) {
                $role->givePermissionTo('view withholding-tax');
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
