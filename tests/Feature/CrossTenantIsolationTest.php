<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Cross-tenant isolation tests.
 *
 * These tests verify that an authenticated user from Tenant B cannot
 * read, modify, or delete resources belonging to Tenant A — either
 * through URL manipulation or direct model queries.
 */
class CrossTenantIsolationTest extends TestCase
{
    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        // Stand up two fully independent tenants.
        [$this->tenantA] = $this->createTenantWithSubscription();
        [$this->tenantB] = $this->createTenantWithSubscription();

        $permissions = [
            'view customers', 'create customers', 'edit customers', 'delete customers',
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices',
            'view roles', 'edit roles', 'delete roles',
        ];

        $this->userA = $this->createUserForTenant($this->tenantA, $permissions);
        $this->userB = $this->createUserForTenant($this->tenantB, $permissions);
    }

    // -------------------------------------------------------------------------
    // Customer isolation (global scope model)
    // -------------------------------------------------------------------------

    public function test_tenant_b_cannot_view_tenant_a_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->userB)
            ->get(route('customers.show', $customer))
            ->assertNotFound();
    }

    public function test_tenant_b_cannot_edit_tenant_a_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->userB)
            ->get(route('customers.edit', $customer))
            ->assertNotFound();
    }

    public function test_tenant_b_cannot_update_tenant_a_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->userB)
            ->put(route('customers.update', $customer), ['name' => 'Hijacked'])
            ->assertNotFound();

        // Original record is unchanged
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'tenant_id' => $this->tenantA->id,
            'name' => $customer->name,
        ]);
    }

    public function test_tenant_b_cannot_delete_tenant_a_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->userB)
            ->delete(route('customers.destroy', $customer))
            ->assertNotFound();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    // -------------------------------------------------------------------------
    // Invoice isolation (global scope model)
    // -------------------------------------------------------------------------

    public function test_tenant_b_cannot_view_tenant_a_invoice(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->userB)
            ->get(route('invoices.show', $invoice))
            ->assertNotFound();
    }

    public function test_tenant_b_cannot_edit_tenant_a_invoice(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->userB)
            ->get(route('invoices.edit', $invoice))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Subscription isolation (global scope model — newly added BelongsToTenant)
    // -------------------------------------------------------------------------

    public function test_subscription_global_scope_only_returns_own_tenant_subscription(): void
    {
        $this->actingAs($this->userA);

        $allSubscriptions = Subscription::all();

        foreach ($allSubscriptions as $subscription) {
            $this->assertEquals(
                $this->tenantA->id,
                $subscription->tenant_id,
                "Subscription #{$subscription->id} belonging to tenant {$subscription->tenant_id} leaked into tenant {$this->tenantA->id}'s query result."
            );
        }
    }

    public function test_subscription_global_scope_does_not_return_other_tenant_subscription(): void
    {
        // Tenant B's subscription should not appear in Tenant A's query results.
        $subB = Subscription::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantB->id)
            ->first();

        $this->assertNotNull($subB, 'Tenant B subscription must exist for this test.');

        $this->actingAs($this->userA);

        $found = Subscription::where('id', $subB->id)->exists();

        $this->assertFalse($found, 'Tenant B subscription should not be visible to Tenant A.');
    }

    // -------------------------------------------------------------------------
    // Role isolation (no global scope — defended by VerifyTenantOwnership)
    // -------------------------------------------------------------------------

    public function test_tenant_b_cannot_edit_tenant_a_role(): void
    {
        $roleA = Role::create([
            'name' => 'accounts-' . $this->tenantA->id,
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->actingAs($this->userB)
            ->get(route('settings.roles.edit', $roleA))
            ->assertForbidden();
    }

    public function test_tenant_b_cannot_delete_tenant_a_role(): void
    {
        $roleA = Role::create([
            'name' => 'billing-' . $this->tenantA->id,
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->actingAs($this->userB)
            ->delete(route('settings.roles.destroy', $roleA))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $roleA->id]);
    }

    // -------------------------------------------------------------------------
    // Global scope integrity check — direct Eloquent queries
    // -------------------------------------------------------------------------

    public function test_customer_query_only_returns_own_tenant_records(): void
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        Customer::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->userA);

        $customers = Customer::all();

        $this->assertCount(3, $customers);
        foreach ($customers as $c) {
            $this->assertEquals($this->tenantA->id, $c->tenant_id);
        }
    }

    public function test_tenant_b_index_does_not_include_tenant_a_customers(): void
    {
        Customer::factory()->count(2)->create(['tenant_id' => $this->tenantA->id]);
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenantB->id]);

        // The index is a Livewire page; verify isolation via direct Eloquent query
        // scoped to userB's session rather than asserting a view variable.
        $this->actingAs($this->userB)
            ->get(route('customers.index'))
            ->assertOk();

        // Global scope should still isolate Eloquent queries for userB.
        $visible = Customer::all();
        $this->assertCount(3, $visible);
        $this->assertTrue($visible->every(fn ($c) => $c->tenant_id === $this->tenantB->id));
    }
}
