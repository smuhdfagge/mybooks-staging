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

    public function test_c1_subscription_past_its_end_date_is_blocked(): void
    {
        $this->subscription->update(['ends_at' => now()->subMinute()]);

        $this->assertFalse($this->tenant->fresh()->hasActiveSubscription());
        $this->get(route('dashboard'))->assertRedirect(route('settings.subscription'));
        $this->getJson('/api/v1/customers')->assertForbidden()->assertJson(['subscription_required' => true]);
    }

    public function test_c1_subscription_is_usable_up_to_its_end_date(): void
    {
        $this->subscription->update(['ends_at' => now()->addMinutes(5)]);

        $this->get(route('dashboard'))->assertOk();
    }

    /** Snapshot of the first Livewire component on $url whose name contains $name. */
    private function livewireSnapshot(string $url, string $name): string
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = collect($matches[1])
            ->map(fn ($s) => html_entity_decode($s))
            ->first(fn ($s) => str_contains(json_decode($s, true)['memo']['name'], $name));
        $this->assertNotNull($snapshot, "{$name} component not found on {$url}");

        return $snapshot;
    }

    private function livewireUpdate(string $snapshot, array $updates = [])
    {
        return $this->withHeaders(['X-Livewire' => '1'])->postJson(\Livewire\Livewire::getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => []]],
        ]);
    }

    public function test_c1_expired_tenant_cannot_keep_using_an_open_livewire_page(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('view customers', 'web');
        $this->user->givePermissionTo('view customers');
        $snapshot = $this->livewireSnapshot(route('customers.index'), 'customers');
        $this->livewireUpdate($snapshot, ['search' => 'x'])->assertOk();

        $this->subscription->update(['ends_at' => now()->subMinute()]);

        $this->livewireUpdate($snapshot, ['search' => 'y'])->assertRedirect(route('settings.subscription'));
    }

    public function test_c1_subscription_page_livewire_still_works_after_expiry(): void
    {
        $snapshot = $this->livewireSnapshot(route('settings.subscription'), 'subscription');
        $this->subscription->update(['ends_at' => now()->subMinute()]);

        $this->livewireUpdate($snapshot, ['cancellationReason' => 'x'])->assertOk();
    }

    public function test_c1_subscription_page_still_works_after_expiry(): void
    {
        $this->subscription->update(['ends_at' => now()->subMinute()]);

        $this->get(route('settings.subscription'))->assertOk();
    }

    public function test_c1_expire_command_closes_ended_subscriptions_and_reminds(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user->assignRole('admin');

        // Ends in 7 days: reminded once, even if the command runs twice
        $this->subscription->update(['ends_at' => now()->addDays(7)->setTime(12, 0)]);
        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->artisan('subscriptions:expire')->assertSuccessful();
        \Illuminate\Support\Facades\Notification::assertSentToTimes($this->user, \App\Notifications\SubscriptionExpiringNotification::class, 1);

        // Ended yesterday: marked expired
        $this->subscription->update(['ends_at' => now()->subDay()]);
        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->assertSame(Subscription::STATUS_EXPIRED, $this->subscription->fresh()->status);
    }

    public function test_c1_expire_command_closes_cancelled_subscriptions_at_the_end(): void
    {
        $this->subscription->cancel();
        $this->subscription->update(['ends_at' => now()->subHour()]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(Subscription::STATUS_EXPIRED, $this->subscription->fresh()->status);
    }

    public function test_c1_grace_command_gives_existing_tenants_time_to_pay(): void
    {
        $this->subscription->update(['ends_at' => now()->subMonths(3)]);

        $this->artisan('subscriptions:grace', ['--days' => 14])->assertSuccessful();

        $subscription = $this->subscription->fresh();
        $this->assertTrue($subscription->ends_at->isAfter(now()->addDays(13)));
        $this->assertArrayHasKey('grace_from', $subscription->metadata);
        $this->assertTrue($this->tenant->fresh()->hasActiveSubscription());
    }
}
