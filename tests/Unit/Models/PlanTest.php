<?php

namespace Tests\Unit\Models;

use App\Models\Plan;
use Tests\TestCase;

class PlanTest extends TestCase
{
    public function test_get_price_for_monthly_cycle(): void
    {
        $plan = Plan::factory()->create([
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
        ]);

        $this->assertEquals(29.99, $plan->getPriceForCycle('monthly'));
    }

    public function test_get_price_for_annual_cycle(): void
    {
        $plan = Plan::factory()->create([
            'monthly_price' => 29.99,
            'annual_price' => 299.99,
        ]);

        $this->assertEquals(299.99, $plan->getPriceForCycle('annual'));
    }

    public function test_allows_billing_cycle_monthly(): void
    {
        $plan = Plan::factory()->create([
            'allow_monthly_billing' => true,
            'allow_annual_billing' => false,
        ]);

        $this->assertTrue($plan->allowsBillingCycle('monthly'));
        $this->assertFalse($plan->allowsBillingCycle('annual'));
    }

    public function test_allows_billing_cycle_annual(): void
    {
        $plan = Plan::factory()->create([
            'allow_monthly_billing' => false,
            'allow_annual_billing' => true,
        ]);

        $this->assertFalse($plan->allowsBillingCycle('monthly'));
        $this->assertTrue($plan->allowsBillingCycle('annual'));
    }

    public function test_scope_active(): void
    {
        Plan::factory()->create(['is_active' => true]);
        Plan::factory()->create(['is_active' => false]);

        $activePlans = Plan::active()->get();
        $this->assertTrue($activePlans->every(fn ($plan) => $plan->is_active === true));
    }

    public function test_subscriptions_relationship(): void
    {
        $this->createAuthenticatedUser();
        $this->assertCount(1, $this->plan->subscriptions);
    }

    public function test_features_cast_as_array(): void
    {
        $features = ['invoicing', 'reporting', 'multi-user'];
        $plan = Plan::factory()->create(['features' => $features]);
        $plan->refresh();

        $this->assertIsArray($plan->features);
        $this->assertEquals($features, $plan->features);
    }
}
