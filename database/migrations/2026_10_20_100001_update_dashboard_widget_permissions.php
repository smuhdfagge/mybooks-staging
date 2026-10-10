<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dashboard upgrade: one permission per dashboard card (see
 * config/dashboard.php), so the roles screen matches what people see.
 *
 * New cards and who gets them on existing businesses, so no one loses a
 * card they had:
 *  - attention-list: every role that can open the dashboard
 *  - top-customers: roles that see income (total-revenue)
 *  - expense-breakdown: roles that see expenses (monthly-expenses)
 *  - cash-position and profit: admins and accountants, and own roles that
 *    already see the revenue chart and the bank accounts
 * The employees count card is gone (the HR pages show employees).
 */
return new class extends Migration
{
    private const NEW = [
        'attention-list dashboard-widgets',
        'cash-position dashboard-widgets',
        'profit dashboard-widgets',
        'top-customers dashboard-widgets',
        'expense-breakdown dashboard-widgets',
    ];

    public function up(): void
    {
        foreach (self::NEW as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->with('permissions')->each(function (Role $role) {
            $has = fn (string $name) => $role->permissions->contains('name', $name);
            $give = [];

            if ($has('view dashboard') || in_array($role->name, ['super-admin', 'admin'], true)) {
                $give[] = 'attention-list dashboard-widgets';
            }
            if ($has('total-revenue dashboard-widgets')) {
                $give[] = 'top-customers dashboard-widgets';
            }
            if ($has('monthly-expenses dashboard-widgets')) {
                $give[] = 'expense-breakdown dashboard-widgets';
            }

            $builtInFinance = $role->tenant_id === null && in_array($role->name, ['super-admin', 'admin', 'accountant'], true);
            $ownFinance = $role->tenant_id !== null && $has('revenue-chart dashboard-widgets') && $has('view banks');
            if ($builtInFinance || $ownFinance || ($role->tenant_id !== null && in_array($role->name, ['admin', 'accountant'], true))) {
                $give[] = 'cash-position dashboard-widgets';
                $give[] = 'profit dashboard-widgets';
            }

            if ($give) {
                $role->givePermissionTo($give);
            }
        });

        Permission::where('name', 'employees-count dashboard-widgets')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::NEW)->where('guard_name', 'web')->delete();
        Permission::firstOrCreate(['name' => 'employees-count dashboard-widgets', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
