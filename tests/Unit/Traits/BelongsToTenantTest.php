<?php

namespace Tests\Unit\Traits;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class BelongsToTenantTest extends TestCase
{
    public function test_auto_sets_tenant_id_on_creation(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals($this->tenant->id, $customer->tenant_id);
    }

    public function test_global_scope_filters_by_tenant(): void
    {
        $this->createAuthenticatedUser();

        // Create customer for authenticated user's tenant
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'My Customer',
        ]);

        // Create customer for different tenant (suppress events to avoid COA conflict)
        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        // Directly insert to bypass BelongsToTenant creating callback
        Customer::withoutEvents(fn () => Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Customer',
        ]));

        // Should only see own tenant's customers
        $customers = Customer::all();
        $this->assertTrue($customers->every(fn ($c) => $c->tenant_id === $this->tenant->id));
    }

    public function test_without_global_scopes_returns_all(): void
    {
        $this->createAuthenticatedUser();

        Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        Customer::withoutEvents(fn () => Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]));

        // withoutGlobalScopes should return all
        $allCustomers = Customer::withoutGlobalScopes()->get();
        $tenantIds = $allCustomers->pluck('tenant_id')->unique();
        $this->assertGreaterThanOrEqual(2, $tenantIds->count());
    }

    public function test_tenant_relationship_exists(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertInstanceOf(Tenant::class, $customer->tenant);
        $this->assertEquals($this->tenant->id, $customer->tenant->id);
    }
}
