<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_is_active_returns_true_for_active_subscription(): void
    {
        $this->createAuthenticatedUser();
        $this->assertTrue($this->subscription->isActive());
    }

    public function test_is_active_returns_false_for_cancelled(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update(['status' => Subscription::STATUS_CANCELLED]);
        $this->assertFalse($this->subscription->isActive());
    }

    public function test_has_expired_returns_false_for_future_end_date(): void
    {
        $this->createAuthenticatedUser();
        $this->assertFalse($this->subscription->hasExpired());
    }

    public function test_has_expired_returns_true_for_past_end_date(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update(['ends_at' => now()->subDay()]);
        $this->assertTrue($this->subscription->hasExpired());
    }

    public function test_is_cancelled_returns_true_with_cancelled_status(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
        $this->assertTrue($this->subscription->isCancelled());
    }

    public function test_is_cancelled_returns_false_for_active(): void
    {
        $this->createAuthenticatedUser();
        $this->assertFalse($this->subscription->isCancelled());
    }

    public function test_days_until_expiration_returns_positive_for_future(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update(['ends_at' => now()->addDays(15)]);
        $days = $this->subscription->daysUntilExpiration();
        $this->assertGreaterThanOrEqual(14, $days);
        $this->assertLessThanOrEqual(15, $days);
    }

    public function test_days_until_expiration_returns_null_when_no_end_date(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update(['ends_at' => null]);
        $this->assertNull($this->subscription->daysUntilExpiration());
    }

    public function test_renew_extends_subscription(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update(['billing_cycle' => 'monthly']);
        $this->subscription->renew();
        $this->subscription->refresh();

        $this->assertEquals(Subscription::STATUS_ACTIVE, $this->subscription->status);
        $this->assertTrue($this->subscription->ends_at->isFuture());
    }

    public function test_renew_annual_extends_by_12_months(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->update(['billing_cycle' => 'annual']);
        $this->subscription->renew();
        $this->subscription->refresh();

        $this->assertEquals(Subscription::STATUS_ACTIVE, $this->subscription->status);
        // Should be ~12 months from now
        $this->assertGreaterThan(now()->addMonths(11), $this->subscription->ends_at);
    }

    public function test_cancel_keeps_the_paid_period_running(): void
    {
        $this->createAuthenticatedUser();
        $this->subscription->cancel('Too expensive');
        $this->subscription->refresh();

        // M1: access continues until ends_at
        $this->assertEquals(Subscription::STATUS_ACTIVE, $this->subscription->status);
        $this->assertTrue($this->subscription->isCancelled());
        $this->assertNotNull($this->subscription->cancelled_at);
        $this->assertEquals('Too expensive', $this->subscription->cancellation_reason);
    }

    public function test_scope_active_filters_by_status(): void
    {
        $this->createAuthenticatedUser();
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => Subscription::STATUS_CANCELLED,
        ]);

        $active = Subscription::active()->get();
        $this->assertTrue($active->every(fn ($s) => $s->status === Subscription::STATUS_ACTIVE));
    }

    public function test_on_trial_always_returns_false(): void
    {
        $this->createAuthenticatedUser();
        $this->assertFalse($this->subscription->onTrial());
    }

    public function test_belongs_to_tenant_relationship(): void
    {
        $this->createAuthenticatedUser();
        $this->assertEquals($this->tenant->id, $this->subscription->tenant->id);
    }

    public function test_belongs_to_plan_relationship(): void
    {
        $this->createAuthenticatedUser();
        $this->assertEquals($this->plan->id, $this->subscription->plan->id);
    }
}
