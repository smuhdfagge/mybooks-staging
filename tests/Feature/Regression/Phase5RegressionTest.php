<?php

namespace Tests\Feature\Regression;

use App\Livewire\Subscriptions\SubscriptionManager;
use App\Models\Subscription;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5: billing. Findings C1 (paying for a subscription) and M1
 * (cancelling keeps access until the paid period ends).
 */
class Phase5RegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser(['manage subscription', 'view dashboard']);
    }

    public function test_m1_cancelling_keeps_access_until_the_end_date(): void
    {
        Livewire::test(SubscriptionManager::class)
            ->set('cancellationReason', 'Closing shop')
            ->call('cancelSubscription');

        $subscription = $this->subscription->fresh();
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($this->tenant->fresh()->hasActiveSubscription());

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_m1_reactivate_works_within_the_period(): void
    {
        $this->subscription->cancel('Changed my mind');

        Livewire::test(SubscriptionManager::class)->call('reactivateSubscription');

        $this->assertNull($this->subscription->fresh()->cancelled_at);
    }

    public function test_m1_reactivate_is_refused_after_the_period_ends(): void
    {
        $this->subscription->cancel();
        $this->subscription->update(['ends_at' => now()->subDay()]);

        $this->assertFalse($this->subscription->fresh()->reactivate());
        $this->assertNotNull($this->subscription->fresh()->cancelled_at);
    }
}
