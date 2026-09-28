<?php

namespace Tests\Feature\Regression;

use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 7: hardening (H4 full, M7, L1, L3 and tidy-ups).
 */
class Phase7RegressionTest extends TestCase
{
    public function test_h4_each_organisation_can_have_its_own_accountant_role(): void
    {
        Permission::findOrCreate('view invoices', 'web');
        Permission::findOrCreate('view payroll', 'web');

        // Organisation A
        $a = $this->createAuthenticatedUser(['create roles']);
        $tenantA = $this->tenant;
        $this->post(route('settings.roles.store'), ['name' => 'Accountant', 'permissions' => ['view invoices']])
            ->assertSessionHasNoErrors();

        // Organisation B, same name, different permissions
        auth()->logout();
        $b = $this->createAuthenticatedUser(['create roles']);
        $tenantB = $this->tenant;
        $this->post(route('settings.roles.store'), ['name' => 'Accountant', 'permissions' => ['view payroll']])
            ->assertSessionHasNoErrors();

        $roleA = Role::where('tenant_id', $tenantA->id)->where('name', 'Accountant')->sole();
        $roleB = Role::where('tenant_id', $tenantB->id)->where('name', 'Accountant')->sole();
        $this->assertNotSame($roleA->id, $roleB->id);

        // Assigning by name picks the signed-in organisation's own role
        $clerkB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $this->actingAs($b);
        $clerkB->assignRole('Accountant');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $clerkB = $clerkB->fresh();
        $this->assertTrue($clerkB->roles->contains($roleB));
        $this->assertTrue($clerkB->can('view payroll'));
        $this->assertFalse($clerkB->can('view invoices'));
    }

    public function test_h4_name_lookup_without_a_user_only_sees_system_roles(): void
    {
        $this->createAuthenticatedUser();
        Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]);
        Role::query()->create(['name' => 'Accountant', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        auth()->logout();

        $this->assertNull(Role::findByName('admin')->tenant_id);
        $this->expectException(\Spatie\Permission\Exceptions\RoleDoesNotExist::class);
        Role::findByName('Accountant');
    }

    public function test_h4_system_role_names_are_reserved(): void
    {
        $this->createAuthenticatedUser(['create roles']);

        $this->post(route('settings.roles.store'), ['name' => 'Admin', 'permissions' => []])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, Role::where('tenant_id', $this->tenant->id)->count());
    }
}
