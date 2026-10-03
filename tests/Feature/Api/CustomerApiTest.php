<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Tenant;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    private function apiHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function createApiUser(array $permissions = []): string
    {
        $this->createAuthenticatedUser($permissions);

        return $this->user->createToken('test-device')->plainTextToken;
    }

    public function test_health_endpoint_is_public(): void
    {
        // Only the access rule is tested here. The backup check reads the real
        // storage/app/backup-status.json, which an earlier local run may have
        // left more than 26 hours old, so it is switched off.
        config(['mybooks.backup.enabled' => false]);
        $response = $this->getJson('/api/v1/health');
        $response->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_unauthenticated_request_gets_401(): void
    {
        $response = $this->getJson('/api/v1/customers');
        $response->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $token = $this->createApiUser(); // no permissions
        $response = $this->withToken($token)->getJson('/api/v1/customers');
        $response->assertForbidden();
    }

    public function test_user_can_list_customers(): void
    {
        $token = $this->createApiUser(['view customers']);

        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($token)->getJson('/api/v1/customers');
        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_user_can_create_customer_via_api(): void
    {
        $token = $this->createApiUser(['create customers']);

        $response = $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'API Customer',
            'email' => 'api@example.com',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('customers', [
            'name' => 'API Customer',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_user_can_view_customer_via_api(): void
    {
        $token = $this->createApiUser(['view customers']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($token)->getJson("/api/v1/customers/{$customer->id}");
        $response->assertOk();
    }

    public function test_user_cannot_view_other_tenant_customer_via_api(): void
    {
        $token = $this->createApiUser(['view customers']);

        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        $otherCustomer = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $otherTenant->id]));

        $response = $this->withToken($token)->getJson("/api/v1/customers/{$otherCustomer->id}");
        $response->assertNotFound();
    }

    public function test_user_without_subscription_gets_403_json(): void
    {
        $token = $this->createApiUser(['view customers']);
        $this->subscription->delete();

        $response = $this->withToken($token)->getJson('/api/v1/customers');
        $response->assertForbidden();
        $response->assertJsonPath('subscription_required', true);
    }

    public function test_api_login(): void
    {
        $this->createAuthenticatedUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
            'device_name' => 'test-device',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['token']]);
    }

    public function test_api_login_invalid_credentials(): void
    {
        $this->createAuthenticatedUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
            'device_name' => 'test-device',
        ]);

        $response->assertUnprocessable();
    }

    public function test_api_search_customers(): void
    {
        $token = $this->createApiUser(['view customers']);

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Searchable Customer',
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Another Client',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/customers?search=Searchable');
        $response->assertOk();
    }
}
