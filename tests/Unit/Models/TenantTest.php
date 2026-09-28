<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class TenantTest extends TestCase
{
    public function test_tenant_has_users_relationship(): void
    {
        $this->createAuthenticatedUser();
        $this->assertInstanceOf(User::class, $this->tenant->users->first());
    }

    public function test_tenant_has_subscriptions_relationship(): void
    {
        $this->createAuthenticatedUser();
        $this->assertCount(1, $this->tenant->subscriptions);
    }

    public function test_tenant_has_active_subscription_returns_true_when_active(): void
    {
        $this->createAuthenticatedUser();
        $this->assertTrue($this->tenant->hasActiveSubscription());
    }

    public function test_tenant_has_active_subscription_returns_false_when_no_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertFalse($tenant->hasActiveSubscription());
    }

    public function test_tenant_has_active_subscription_returns_false_when_cancelled(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update(['status' => Subscription::STATUS_CANCELLED]);
        $this->tenant->refresh();
        $this->assertFalse($this->tenant->hasActiveSubscription());
    }

    public function test_tenant_can_add_users_within_plan_limit(): void
    {
        $this->createAuthenticatedUser();
        $this->plan->update(['max_users' => 5]);
        // tenant already has 1 user (the authenticated one)
        $this->assertTrue($this->tenant->canAddUsers(1));
    }

    public function test_tenant_cannot_add_users_beyond_plan_limit(): void
    {
        $this->createAuthenticatedUser();
        $this->plan->update(['max_users' => 1]);
        // tenant already has 1 user, can't add more
        $this->assertFalse($this->tenant->canAddUsers(1));
    }

    public function test_tenant_cannot_add_users_without_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertFalse($tenant->canAddUsers());
    }

    public function test_tenant_remaining_user_slots(): void
    {
        $this->createAuthenticatedUser();
        $this->plan->update(['max_users' => 5]);
        // 1 user created, so 4 slots remaining
        $this->assertEquals(4, $this->tenant->remainingUserSlots());
    }

    public function test_tenant_remaining_slots_zero_without_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertEquals(0, $tenant->remainingUserSlots());
    }

    public function test_subscription_status_attribute_active(): void
    {
        $this->createAuthenticatedUser();
        $this->assertEquals('Active', $this->tenant->subscription_status);
    }

    public function test_subscription_status_attribute_no_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertEquals('No Subscription', $tenant->subscription_status);
    }

    public function test_currency_symbol_attribute(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'NGN']);
        $this->assertEquals('₦', $tenant->currency_symbol);

        $tenant2 = Tenant::factory()->create(['currency' => 'USD']);
        $this->assertEquals('$', $tenant2->currency_symbol);
    }

    public function test_tenant_creates_default_chart_of_accounts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertGreaterThan(0, $tenant->accounts()->count());
    }

    public function test_tenant_soft_deletes(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantId = $tenant->id;
        $tenant->delete();
        $this->assertSoftDeleted('tenants', ['id' => $tenantId]);
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $this->assertIsBool($tenant->is_active);
    }
}
