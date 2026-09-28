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

    public function test_m7_custom_report_rejects_columns_outside_the_data_source(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $base = ['name' => 'Test', 'data_source' => 'invoices', 'columns' => ['invoice_number', 'total']];

        $this->post(route('reports.custom.store'), array_merge($base, ['columns' => ['invoice_number', 'tenant_id']]))
            ->assertSessionHasErrors('columns.1');
        $this->post(route('reports.custom.store'), array_merge($base, ['filters' => [['column' => 'deleted_at', 'operator' => 'is_null']]]))
            ->assertSessionHasErrors('filters.0.column');
        $this->post(route('reports.custom.store'), array_merge($base, ['sort_by' => [['column' => 'total', 'direction' => 'sideways']]]))
            ->assertSessionHasErrors('sort_by.0.direction');
        $this->post(route('reports.custom.store'), array_merge($base, ['aggregations' => [['column' => 'total', 'function' => 'drop']]]))
            ->assertSessionHasErrors('aggregations.0.function');
        $this->post(route('reports.custom.store'), array_merge($base, ['data_source' => 'users']))
            ->assertSessionHasErrors('data_source');
        $this->assertSame(0, \App\Models\CustomReport::count());

        $this->post(route('reports.custom.store'), $base + [
            'filters' => [['column' => 'status', 'operator' => 'equals', 'value' => 'sent']],
            'sort_by' => [['column' => 'total', 'direction' => 'desc']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\CustomReport::count());
    }

    public function test_m7_old_reports_with_unknown_columns_still_run(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $report = \App\Models\CustomReport::create([
            'tenant_id' => $this->tenant->id, 'created_by' => $this->user->id, 'name' => 'Old', 'data_source' => 'invoices',
            'columns' => ['invoice_number'],
            'filters' => [['column' => 'no_such_column', 'operator' => 'equals', 'value' => 'x']],
            'sort_by' => [['column' => 'no_such_column', 'direction' => 'asc']],
        ]);

        $this->get(route('reports.custom.run', $report))->assertOk();
    }

    public function test_l1_salary_structure_form_escapes_old_input(): void
    {
        $this->createAuthenticatedUser(['create payroll', 'view payroll']);
        $evil = "1';alert(document.cookie);//</script><script>alert(1)</script>";

        $html = $this->withSession(['_old_input' => ['basic_salary' => $evil]])
            ->get(route('salary-structures.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString("'1';alert(document.cookie)", $html);
        $this->assertStringNotContainsString('{!!', file_get_contents(resource_path('views/payroll/salary-structures/create.blade.php')));
        $this->assertStringNotContainsString('{!!', file_get_contents(resource_path('views/payroll/salary-structures/edit.blade.php')));
    }

    public function test_l1_salary_structure_edit_form_renders(): void
    {
        $this->createAuthenticatedUser(['create payroll', 'edit payroll', 'view payroll']);
        $structure = \App\Models\SalaryStructure::withoutEvents(fn () => \App\Models\SalaryStructure::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Standard', 'basic_salary' => 5000, 'is_active' => true,
            'effective_from' => now()->startOfYear(), 'version' => 1, 'created_by' => $this->user->id,
        ]));

        $this->get(route('salary-structures.edit', $structure))->assertOk()->assertSee('basicSalary: ', false);
    }
}
