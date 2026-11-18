<?php

namespace Tests\Feature\Regression;

use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The built-in Accountant role runs payroll (owner's decision, November
 * 2026): every payroll permission and the staff records a pay run needs,
 * on a fresh install (seeder) and on one already running (migration). A
 * role a business made for itself keeps what the business gave it.
 */
class AccountantPayrollTest extends TestCase
{
    private const PAYROLL = ['view payroll', 'create payroll', 'edit payroll', 'approve payroll', 'delete payroll', 'view employees', 'edit employees'];

    private function migration(): object
    {
        return require database_path('migrations/2026_11_18_100000_give_the_accountant_role_payroll.php');
    }

    public function test_a_fresh_install_gives_the_accountant_role_payroll(): void
    {
        $this->seed(DatabaseSeeder::class);
        $accountant = Role::whereNull('tenant_id')->where('name', 'accountant')->firstOrFail();

        foreach (self::PAYROLL as $permission) {
            $this->assertTrue($accountant->hasPermissionTo($permission), $permission);
        }
        // Creating and deleting staff stays with HR and the admins.
        $this->assertFalse($accountant->hasPermissionTo('create employees'));
        $this->assertFalse($accountant->hasPermissionTo('delete employees'));
    }

    public function test_the_migration_gives_an_existing_accountant_role_payroll_and_leaves_a_businesss_own_role(): void
    {
        $this->seed(DatabaseSeeder::class);
        $system = Role::whereNull('tenant_id')->where('name', 'accountant')->firstOrFail();
        $system->revokePermissionTo(self::PAYROLL);

        $this->createAuthenticatedUser();
        $own = Role::create(['name' => 'accountant', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $own->givePermissionTo('view reports');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->migration()->up();

        $system->refresh();
        foreach (self::PAYROLL as $permission) {
            $this->assertTrue($system->hasPermissionTo($permission), $permission);
        }
        $this->assertSame(['view reports'], $own->fresh()->permissions->pluck('name')->all(), "A business's own role keeps what it was given.");

        // And back.
        $this->migration()->down();
        $this->assertFalse($system->fresh()->hasPermissionTo('view payroll'));
    }

    public function test_the_migration_runs_both_ways_on_an_install_missing_one_of_the_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        Permission::where('name', 'delete payroll')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->migration()->up();
        $this->assertTrue(Role::whereNull('tenant_id')->where('name', 'accountant')->firstOrFail()->hasPermissionTo('view payroll'));

        $this->migration()->down();
        $this->assertFalse(Role::whereNull('tenant_id')->where('name', 'accountant')->firstOrFail()->hasPermissionTo('view payroll'));
    }
}
