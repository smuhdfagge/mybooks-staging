<?php

namespace Tests\Feature\Regression;

use App\Livewire\Subscriptions\SubscriptionManager;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression tests for the Phase 2 (permissions) fixes.
 */
class Phase2RegressionTest extends TestCase
{
    // ── C1 (part): only admins manage the subscription ──────────

    public function test_c1_user_without_permission_cannot_change_cancel_or_reactivate_the_plan(): void
    {
        $this->createAuthenticatedUser();
        $other = Plan::factory()->create(['slug' => 'enterprise-test']);
        $originalPlan = $this->subscription->plan_id;

        Livewire::test(SubscriptionManager::class)
            ->set('selectedPlanId', $other->id)
            ->set('selectedBillingCycle', 'monthly')
            ->call('changePlan')
            ->assertForbidden();

        Livewire::test(SubscriptionManager::class)->call('cancelSubscription')->assertForbidden();
        Livewire::test(SubscriptionManager::class)->call('reactivateSubscription')->assertForbidden();

        $this->assertSame($originalPlan, $this->tenant->fresh()->activeSubscription->plan_id);
        $this->assertNull($this->subscription->fresh()->cancelled_at);
    }

    public function test_c1_user_with_permission_can_change_the_plan(): void
    {
        $this->createAuthenticatedUser(['manage subscription']);
        $other = Plan::factory()->create(['slug' => 'enterprise-test']);

        Livewire::test(SubscriptionManager::class)
            ->set('selectedPlanId', $other->id)
            ->set('selectedBillingCycle', 'monthly')
            ->call('changePlan')
            ->assertStatus(200);

        $this->assertSame($other->id, $this->tenant->fresh()->activeSubscription->plan_id);
    }

    public function test_c1_page_hides_plan_buttons_from_non_admins(): void
    {
        $this->createAuthenticatedUser();

        Livewire::test(SubscriptionManager::class)
            ->assertDontSeeHtml('wire:click="openUpgradeModal')
            ->assertDontSeeHtml('wire:click="openCancelModal"')
            ->assertSee('Only an administrator can change or cancel the subscription.');

        $this->user->givePermissionTo(Permission::findOrCreate('manage subscription', 'web'));

        Livewire::test(SubscriptionManager::class)
            ->assertSeeHtml('wire:click="openUpgradeModal')
            ->assertSeeHtml('wire:click="openCancelModal"');
    }

    public function test_c1_manage_subscription_permission_is_seeded_for_admins_only(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('manage subscription'));
        $this->assertTrue(Role::findByName('super-admin', 'web')->hasPermissionTo('manage subscription'));
        foreach (['accountant', 'sales', 'hr-manager', 'viewer'] as $role) {
            $this->assertFalse(Role::findByName($role, 'web')->hasPermissionTo('manage subscription'), $role);
        }
    }

    public function test_c1_migration_grants_manage_subscription_to_existing_admin_roles(): void
    {
        Permission::where('name', 'manage subscription')->delete();
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        (require database_path('migrations/2026_09_28_000003_add_manage_subscription_permission.php'))->up();

        $this->assertTrue($admin->fresh()->hasPermissionTo('manage subscription'));
        $this->assertFalse($viewer->fresh()->hasPermissionTo('manage subscription'));
    }

    // ── H4 (quick fix): role assignment ─────────────────────────

    /** A second organisation, created while someone is signed in. */
    private function otherTenant()
    {
        return \App\Models\Customer::withoutTenantGuard(fn () => $this->createTenantWithSubscription()[0]);
    }

    private function makeRoles(): array
    {
        $otherTenant = $this->otherTenant();
        Permission::findOrCreate('delete invoices', 'web');

        $global = Role::create(['name' => 'accountant', 'guard_name' => 'web']);
        $own = Role::create(['name' => 'My Clerk', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $foreign = Role::create(['name' => 'Their Auditor', 'guard_name' => 'web', 'tenant_id' => $otherTenant->id]);
        $foreign->givePermissionTo('delete invoices');
        $super = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

        return [$global, $own, $foreign, $super];
    }

    private function userPayload(array $roleIds, array $extra = []): array
    {
        return array_merge([
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'Str0ng#Passw0rd!x',
            'password_confirmation' => 'Str0ng#Passw0rd!x',
            'roles' => $roleIds,
        ], $extra);
    }

    public function test_h4_can_assign_own_and_system_roles(): void
    {
        $this->createAuthenticatedUser(['create users']);
        [$global, $own] = $this->makeRoles();

        $this->post(route('settings.users.store'), $this->userPayload([$global->id, $own->id]))
            ->assertRedirect(route('settings.users'));

        $created = User::where('email', 'new.person@example.com')->firstOrFail();
        $this->assertEqualsCanonicalizing(['accountant', 'My Clerk'], $created->getRoleNames()->all());
    }

    public function test_h4_cannot_assign_another_tenants_role_or_super_admin(): void
    {
        $this->createAuthenticatedUser(['create users']);
        [, , $foreign, $super] = $this->makeRoles();

        foreach ([$foreign, $super] as $role) {
            $this->post(route('settings.users.store'), $this->userPayload([$role->id]))
                ->assertSessionHasErrors('roles.0');
        }
        $this->assertNull(User::where('email', 'new.person@example.com')->first());

        // Old name-based payloads are rejected too
        $this->post(route('settings.users.store'), $this->userPayload(['Their Auditor']))
            ->assertSessionHasErrors('roles.0');
    }

    public function test_h4_user_cannot_change_own_roles_or_deactivate_self(): void
    {
        $this->createAuthenticatedUser(['edit users']);
        [$global, $own] = $this->makeRoles();
        $this->user->assignRole($own);

        $this->put(route('settings.users.update', $this->user), [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'is_active' => false,
            'roles' => [$global->id],
        ])->assertRedirect(route('settings.users'));

        $this->user->refresh();
        $this->assertSame(['My Clerk'], $this->user->getRoleNames()->all());
        $this->assertTrue((bool) $this->user->is_active);
    }

    public function test_h4_editing_another_user_keeps_roles_the_editor_cannot_assign(): void
    {
        $this->createAuthenticatedUser(['edit users']);
        [$global, $own, , $super] = $this->makeRoles();
        $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $other->assignRole($super);

        $this->put(route('settings.users.update', $other), [
            'name' => $other->name,
            'email' => $other->email,
            'roles' => [$own->id],
        ])->assertRedirect(route('settings.users'));

        $this->assertEqualsCanonicalizing(['super-admin', 'My Clerk'], $other->fresh()->getRoleNames()->all());
    }

    public function test_h4_edit_form_lists_only_assignable_roles(): void
    {
        $this->createAuthenticatedUser(['edit users']);
        $this->makeRoles();
        $other = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->get(route('settings.users.edit', $other))
            ->assertOk()
            ->assertSee('My Clerk')
            ->assertSee('accountant')
            ->assertDontSee('Their Auditor')
            ->assertDontSee('super-admin');
    }

    public function test_h4_duplicate_role_name_gives_a_form_error_not_a_crash(): void
    {
        $this->createAuthenticatedUser(['create roles']);
        Role::create(['name' => 'Cashier', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        // Same organisation: a form error. (Another organisation's "Cashier"
        // no longer blocks the name, see Phase7RegressionTest.)
        $this->post(route('settings.roles.store'), ['name' => 'Cashier', 'permissions' => []])
            ->assertSessionHasErrors('name');
    }

    // ── L12: global search respects permissions ─────────────────

    public function test_l12_global_search_only_shows_sections_the_user_can_view(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        \App\Models\Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Zebra Customer']);
        \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Zebra Vendor']);

        Livewire::test(\App\Livewire\GlobalSearch::class)
            ->set('query', 'Zebra')
            ->assertSee('Zebra Customer')
            ->assertDontSee('Zebra Vendor');
    }

    public function test_l12_global_search_stays_inside_the_tenant(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        $other = $this->otherTenant();
        \App\Models\Customer::withoutTenantGuard(fn () => \App\Models\Customer::factory()->create([
            'tenant_id' => $other->id, 'name' => 'Zebra Elsewhere', 'email' => 'zebra@elsewhere.test',
        ]));

        Livewire::test(\App\Livewire\GlobalSearch::class)
            ->set('query', 'zebra@')
            ->assertDontSee('Zebra Elsewhere');
    }

    // ── L13: invoice template editor ────────────────────────────

    public function test_l13_template_editor_requires_edit_settings_to_save(): void
    {
        $this->createAuthenticatedUser(['view settings']);

        Livewire::test(\App\Livewire\Settings\InvoiceTemplateEditor::class)
            ->set('name', 'Mine')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, \App\Models\InvoiceTemplate::count());
    }

    public function test_l13_template_colours_and_font_are_validated(): void
    {
        $this->createAuthenticatedUser(['edit settings']);

        Livewire::test(\App\Livewire\Settings\InvoiceTemplateEditor::class)
            ->set('name', 'Mine')
            ->set('primary_color', 'red;}')
            ->set('font_family', 'x; } body { display:none')
            ->call('save')
            ->assertHasErrors(['primary_color', 'font_family']);

        $this->assertSame(0, \App\Models\InvoiceTemplate::count());
    }

    public function test_l13_valid_template_saves(): void
    {
        $this->createAuthenticatedUser(['edit settings']);

        Livewire::test(\App\Livewire\Settings\InvoiceTemplateEditor::class)
            ->set('name', 'Mine')
            ->set('primary_color', '#123ABC')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, \App\Models\InvoiceTemplate::count());
    }

    // ── L2: validation stays inside the tenant ─────────────────

    private function transferFor(int $tenantId, string $status): \App\Models\StockTransfer
    {
        return \App\Models\StockTransfer::withoutTenantGuard(function () use ($tenantId, $status) {
            $from = \App\Models\Warehouse::create(['tenant_id' => $tenantId, 'name' => 'A', 'code' => 'A'.$tenantId]);
            $to = \App\Models\Warehouse::create(['tenant_id' => $tenantId, 'name' => 'B', 'code' => 'B'.$tenantId]);
            $item = \App\Models\Item::factory()->create(['tenant_id' => $tenantId, 'track_inventory' => false]);
            $transfer = \App\Models\StockTransfer::create([
                'tenant_id' => $tenantId, 'transfer_number' => 'ST-'.$tenantId,
                'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'status' => $status,
            ]);
            $transfer->items()->create(['item_id' => $item->id, 'quantity' => 5, 'quantity_received' => 0]);

            return $transfer;
        });
    }

    public function test_l2_receiving_a_transfer_cannot_touch_another_tenants_transfer_lines(): void
    {
        config(['mybooks.features.stock_transfers' => true]);   // hidden by default since Phase 6 (N4)
        $this->createAuthenticatedUser(['adjust inventory']);
        $mine = $this->transferFor($this->tenant->id, \App\Models\StockTransfer::STATUS_IN_TRANSIT);
        $theirs = $this->transferFor($this->otherTenant()->id, \App\Models\StockTransfer::STATUS_IN_TRANSIT);
        $theirLine = $theirs->items()->first();

        $this->post(route('stock-transfers.receive', $mine), [
            'items' => [['id' => $theirLine->id, 'quantity_received' => 999]],
        ])->assertSessionHasErrors('items.0.id');

        $this->assertEquals(0, (float) $theirLine->fresh()->quantity_received);
    }

    public function test_l2_api_employee_rejects_another_tenants_department(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->givePermissionTo(Permission::findOrCreate('create employees', 'web'));
        $other = \App\Models\Customer::withoutTenantGuard(fn () => $this->createTenantWithSubscription()[0]);
        $foreignDept = \App\Models\Department::withoutTenantGuard(fn () => \App\Models\Department::create([
            'tenant_id' => $other->id, 'name' => 'Theirs', 'code' => 'THR',
        ]));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/employees', ['first_name' => 'A', 'last_name' => 'B', 'department_id' => $foreignDept->id])
            ->assertJsonValidationErrors('department_id');
    }
}
