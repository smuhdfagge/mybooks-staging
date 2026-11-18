<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The built-in Accountant role runs payroll too (owner's decision,
 * November 2026): every payroll permission, and the staff records a pay
 * run needs. Creating and deleting staff stays with HR and the admins.
 *
 * Only the shared system role (tenant_id null). A role a business made for
 * itself, even one it called "accountant", stays as the business set it.
 */
return new class extends Migration
{
    private array $permissions = [
        'view payroll', 'create payroll', 'edit payroll', 'approve payroll', 'delete payroll',
        'view employees', 'edit employees',
    ];

    public function up(): void
    {
        $role = Role::query()->whereNull('tenant_id')->where('name', 'accountant')->where('guard_name', 'web')->first();
        if ($role === null) {
            return; // A fresh install: DatabaseSeeder gives it these.
        }

        // Only permissions this install has; a missing one is not created
        // here. The models themselves, so no stale permission cache is asked.
        $role->givePermissionTo($this->existing());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->whereNull('tenant_id')->where('name', 'accountant')->where('guard_name', 'web')->first();
        if ($role !== null) {
            $role->permissions()->detach($this->existing()->modelKeys());
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function existing()
    {
        return Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->get();
    }
};
