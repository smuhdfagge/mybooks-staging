<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Payroll > Statutory remittances and Statutory settings.
 * - view statutory-remittances: roles that can view payroll
 * - record statutory-remittances: roles that can edit payroll
 * - manage statutory-settings: roles that can edit payroll and admins
 */
return new class extends Migration
{
    private array $grants = [
        'view statutory-remittances' => 'view payroll',
        'record statutory-remittances' => 'edit payroll',
        'manage statutory-settings' => 'edit payroll',
    ];

    public function up(): void
    {
        foreach (array_keys($this->grants) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->with('permissions')->each(function (Role $role) {
            $grant = [];
            foreach ($this->grants as $name => $existing) {
                if ($role->permissions->contains('name', $existing) || in_array($role->name, ['super-admin', 'admin'], true)) {
                    $grant[] = $name;
                }
            }
            if ($grant) {
                $role->givePermissionTo($grant);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys($this->grants))->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
