<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get(route('customers.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_gets_403(): void
    {
        $this->createAuthenticatedUser(); // no permissions
        $response = $this->get(route('customers.index'));
        $response->assertForbidden();
    }

    public function test_user_with_permission_can_view_index(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        $response = $this->get(route('customers.index'));
        $response->assertOk();
    }

    public function test_user_can_create_customer(): void
    {
        $this->createAuthenticatedUser(['create customers']);

        $response = $this->post(route('customers.store'), [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '1234567890',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_customer_creation_requires_name(): void
    {
        $this->createAuthenticatedUser(['create customers']);

        $response = $this->post(route('customers.store'), [
            'email' => 'customer@example.com',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_user_can_view_own_tenant_customer(): void
    {
        $this->createAuthenticatedUser(['view customers']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->get(route('customers.show', $customer));
        $response->assertOk();
    }

    public function test_user_cannot_view_other_tenant_customer(): void
    {
        $this->createAuthenticatedUser(['view customers']);

        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        $otherCustomer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $otherTenant->id]));

        $response = $this->get(route('customers.show', $otherCustomer));
        $response->assertNotFound();
    }

    public function test_user_can_update_customer(): void
    {
        $this->createAuthenticatedUser(['edit customers']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->put(route('customers.update', $customer), [
            'name' => 'Updated Customer',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Customer',
        ]);
    }

    public function test_user_cannot_update_other_tenant_customer(): void
    {
        $this->createAuthenticatedUser(['edit customers']);

        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        $otherCustomer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $otherTenant->id]));

        $response = $this->put(route('customers.update', $otherCustomer), [
            'name' => 'Hacked Update',
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_delete_customer(): void
    {
        $this->createAuthenticatedUser(['delete customers']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->delete(route('customers.destroy', $customer));
        $response->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_user_cannot_delete_other_tenant_customer(): void
    {
        $this->createAuthenticatedUser(['delete customers']);

        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        $otherCustomer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $otherTenant->id]));

        $response = $this->delete(route('customers.destroy', $otherCustomer));
        $response->assertNotFound();
    }

    public function test_user_without_subscription_is_redirected(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        // Remove the subscription
        $this->subscription->delete();

        $response = $this->get(route('customers.index'));
        $response->assertRedirect(route('settings.subscription'));
    }
}
