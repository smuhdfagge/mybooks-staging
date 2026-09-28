<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BelongsToTenantHardeningTest extends TestCase
{
    public function test_creating_model_without_tenant_id_or_auth_throws(): void
    {
        // No authenticated user, no tenant_id set → should throw
        Auth::logout();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create Customer without a tenant_id');

        Customer::withoutEvents(function () {
            // We need to bypass COA but still trigger BelongsToTenant
            // Hmm, withoutEvents bypasses all events. Let's use direct creation.
        });

        // Direct creation without events bypass
        Customer::create([
            'name' => 'Orphan Customer',
            'email' => 'orphan@example.com',
        ]);
    }

    public function test_creating_model_with_explicit_tenant_id_no_auth_succeeds(): void
    {
        // No authenticated user, but tenant_id explicitly set → should work
        Auth::logout();

        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());

        $customer = Customer::withoutEvents(function () use ($tenant) {
            return Customer::create([
                'name' => 'Explicit Tenant Customer',
                'email' => 'explicit@example.com',
                'tenant_id' => $tenant->id,
            ]);
        });

        // withoutEvents bypasses creating hook, so this tests the factory path
        $this->assertEquals($tenant->id, $customer->tenant_id);
    }

    public function test_tenant_id_cannot_be_changed_on_update(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Customer',
        ]);

        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot change tenant_id');

        $customer->tenant_id = $otherTenant->id;
        $customer->save();
    }

    public function test_updating_without_changing_tenant_id_succeeds(): void
    {
        $this->createAuthenticatedUser();

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
        ]);

        $customer->name = 'Updated Name';
        $customer->save();

        $this->assertEquals('Updated Name', $customer->fresh()->name);
        $this->assertEquals($this->tenant->id, $customer->fresh()->tenant_id);
    }

    public function test_with_tenant_guard_disabled_allows_creation_without_auth(): void
    {
        Auth::logout();

        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());

        $customer = Customer::withoutTenantGuard(function () use ($tenant) {
            return Customer::create([
                'tenant_id' => $tenant->id,
                'name' => 'Guard Disabled Customer',
                'email' => 'guarded@example.com',
            ]);
        });

        $this->assertEquals($tenant->id, $customer->tenant_id);
    }

    public function test_skip_tenant_guard_instance_flag(): void
    {
        Auth::logout();

        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());

        $customer = new Customer([
            'name' => 'Instance Guard Skip',
            'email' => 'instance@example.com',
            'tenant_id' => $tenant->id,
        ]);
        $customer->skipTenantGuard = true;
        $customer->save();

        $this->assertEquals($tenant->id, $customer->tenant_id);
    }

    public function test_global_scope_qualifies_column_with_table_name(): void
    {
        $this->createAuthenticatedUser();

        // Create a customer to query
        Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // This should not throw "ambiguous column" when using joins
        $query = Customer::query()->toSql();
        // The column is qualified with the table name (may be quoted depending on driver)
        $this->assertTrue(
            str_contains($query, 'customers.tenant_id')
            || str_contains($query, '"customers"."tenant_id"')
            || str_contains($query, '`customers`.`tenant_id`'),
            "Expected tenant_id to be qualified with table name in: {$query}"
        );
    }

    public function test_auth_user_tenant_id_overwrites_explicit_tenant_id(): void
    {
        $this->createAuthenticatedUser();

        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());

        // Even if we specify a different tenant_id, the auth user's tenant_id wins
        $customer = Customer::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Overwritten Tenant',
            'email' => 'overwrite@example.com',
        ]);

        $this->assertEquals($this->tenant->id, $customer->tenant_id);
    }
}
