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

    // ---- C1: paying through Paystack -------------------------------------

    private const SECRET = 'sk_test_phase5';

    private function paystack(): void
    {
        config(['services.paystack.secret_key' => self::SECRET, 'services.paystack.currency' => 'NGN']);
    }

    /** Make the setUp subscription a sign-up that hasn't been paid. */
    private function makePending(float $monthly = 5000): void
    {
        $this->plan->update(['monthly_price' => $monthly, 'annual_price' => $monthly * 10]);
        $this->subscription->forceFill([
            'status' => Subscription::STATUS_PENDING, 'billing_cycle' => 'monthly', 'amount' => $monthly,
            'ends_at' => null,
        ])->save();
    }

    private function fakeInitialize(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.paystack.co/transaction/initialize' => \Illuminate\Support\Facades\Http::response([
                'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123'],
            ]),
        ]);
    }

    private function startPayment(): \App\Models\SubscriptionPayment
    {
        $this->fakeInitialize();
        Livewire::test(SubscriptionManager::class)
            ->call('payPending')
            ->assertRedirect('https://checkout.paystack.com/abc123');

        return \App\Models\SubscriptionPayment::latest('id')->firstOrFail();
    }

    private function signedWebhook(array $event)
    {
        $body = json_encode($event);

        return $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::SECRET),
        ], $body);
    }

    private function charge(\App\Models\SubscriptionPayment $payment, array $overrides = []): array
    {
        return array_merge([
            'status' => 'success', 'reference' => $payment->reference,
            'amount' => $payment->amountInKobo(), 'currency' => 'NGN', 'paid_at' => now()->toIso8601String(),
        ], $overrides);
    }

    public function test_c1_sign_up_waits_for_payment(): void
    {
        auth()->logout();
        $plan = \App\Models\Plan::factory()->create(['monthly_price' => 5000, 'annual_price' => 50000]);

        $this->post('/register', [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'company_name' => 'Kano Traders', 'company_email' => 'office@kanotraders.test', 'currency' => 'NGN',
            'name' => 'Aisha', 'email' => 'aisha@kanotraders.test',
            'password' => $password = 'Aa1!'.\Illuminate\Support\Str::random(16), 'password_confirmation' => $password,
        ])->assertRedirect(route('verification.notice', absolute: false));

        $user = \App\Models\User::where('email', 'aisha@kanotraders.test')->firstOrFail();
        $user->markEmailAsVerified();
        $subscription = Subscription::withoutGlobalScopes()->where('tenant_id', $user->tenant_id)->sole();

        $this->assertSame(Subscription::STATUS_PENDING, $subscription->status);
        $this->assertNull($subscription->ends_at);
        $this->assertFalse($user->tenant->hasActiveSubscription());

        \Spatie\Permission\Models\Permission::findOrCreate('view dashboard', 'web');
        $user->givePermissionTo('view dashboard');
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('settings.subscription'));
    }

    public function test_c1_free_plan_is_active_at_sign_up(): void
    {
        auth()->logout();
        $plan = \App\Models\Plan::factory()->create(['monthly_price' => 0, 'annual_price' => 0]);

        $this->post('/register', [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'company_name' => 'Free Co', 'company_email' => 'office@free.test', 'currency' => 'NGN',
            'name' => 'Musa', 'email' => 'musa@free.test',
            'password' => $password = 'Aa1!'.\Illuminate\Support\Str::random(16), 'password_confirmation' => $password,
        ]);

        $this->assertTrue(\App\Models\User::where('email', 'musa@free.test')->firstOrFail()->tenant->hasActiveSubscription());
    }

    public function test_c1_changing_plan_no_longer_switches_without_paying(): void
    {
        $this->paystack();
        $this->fakeInitialize();
        $bigger = \App\Models\Plan::factory()->create(['monthly_price' => 20000, 'annual_price' => 200000]);

        Livewire::test(SubscriptionManager::class)
            ->set('selectedPlanId', $bigger->id)
            ->set('selectedBillingCycle', 'monthly')
            ->call('changePlan')
            ->assertRedirect('https://checkout.paystack.com/abc123');

        // Still on the old plan until Paystack confirms
        $this->assertSame($this->plan->id, $this->tenant->fresh()->currentPlan()->id);
        $payment = \App\Models\SubscriptionPayment::sole();
        $this->assertSame(2000000, $payment->amountInKobo());
        $this->assertSame('pending', $payment->status);

        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request['amount'] === 2000000
            && $request['currency'] === 'NGN'
            && $request->hasHeader('Authorization', 'Bearer '.self::SECRET));
    }

    public function test_c1_callback_checks_with_paystack_before_activating(): void
    {
        $this->paystack();
        $this->makePending();
        $payment = $this->startPayment();

        // Paystack says the charge failed: the redirect alone changes nothing
        \Illuminate\Support\Facades\Http::fake([
            'api.paystack.co/transaction/verify/*' => \Illuminate\Support\Facades\Http::response([
                'status' => true, 'data' => $this->charge($payment, ['status' => 'failed']),
            ]),
        ]);
        $this->get(route('billing.callback', ['reference' => $payment->reference]))
            ->assertRedirect(route('settings.subscription'));
        $this->assertFalse($this->tenant->fresh()->hasActiveSubscription());
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_c1_callback_activates_a_paid_sign_up(): void
    {
        $this->paystack();
        $this->makePending();
        $payment = $this->startPayment();

        \Illuminate\Support\Facades\Http::fake([
            'api.paystack.co/transaction/verify/*' => \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => $this->charge($payment)]),
        ]);
        $this->get(route('billing.callback', ['reference' => $payment->reference]))
            ->assertRedirect(route('settings.subscription'))
            ->assertSessionHas('success');

        $subscription = $this->subscription->fresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->ends_at->between(now()->addMonth()->subMinute(), now()->addMonth()->addMinute()));
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame($subscription->id, $payment->fresh()->subscription_id);
    }

    public function test_c1_webhook_with_a_bad_signature_is_rejected(): void
    {
        $this->paystack();
        $this->makePending();
        $payment = $this->startPayment();

        $body = json_encode(['event' => 'charge.success', 'data' => $this->charge($payment)]);
        $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'not-the-secret'),
        ], $body)->assertStatus(401);

        $this->call('POST', route('billing.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(401);

        $this->assertFalse($this->tenant->fresh()->hasActiveSubscription());
    }

    public function test_c1_signed_webhook_activates_once_even_if_sent_twice(): void
    {
        $this->paystack();
        $this->makePending();
        $payment = $this->startPayment();
        auth()->logout();   // Paystack's server isn't logged in

        $event = ['event' => 'charge.success', 'data' => $this->charge($payment)];
        $this->signedWebhook($event)->assertOk();
        $endsAt = $this->subscription->fresh()->ends_at;
        $this->signedWebhook($event)->assertOk();

        $this->assertTrue($this->tenant->fresh()->hasActiveSubscription());
        $this->assertEquals($endsAt, $this->subscription->fresh()->ends_at);
    }

    public function test_c1_underpaid_or_wrong_currency_charge_is_not_accepted(): void
    {
        $this->paystack();
        $this->makePending();
        $payment = $this->startPayment();

        $this->signedWebhook(['event' => 'charge.success', 'data' => $this->charge($payment, ['amount' => 100])])->assertOk();
        $this->signedWebhook(['event' => 'charge.success', 'data' => $this->charge($payment, ['currency' => 'USD'])])->assertOk();

        $this->assertFalse($this->tenant->fresh()->hasActiveSubscription());
        $this->assertNotSame('success', $payment->fresh()->status);
    }

    public function test_c1_renewing_adds_a_period_to_the_time_left(): void
    {
        $this->paystack();
        $this->fakeInitialize();
        $this->subscription->update(['billing_cycle' => 'monthly', 'ends_at' => now()->addDays(5)]);
        $oldEnd = $this->subscription->fresh()->ends_at;

        Livewire::test(SubscriptionManager::class)->call('renew')->assertRedirect('https://checkout.paystack.com/abc123');
        $payment = \App\Models\SubscriptionPayment::sole();
        $this->signedWebhook(['event' => 'charge.success', 'data' => $this->charge($payment)])->assertOk();

        $this->assertEquals($oldEnd->copy()->addMonth()->toDateTimeString(), $this->subscription->fresh()->ends_at->toDateTimeString());
    }

    public function test_c1_lapsed_subscription_can_be_renewed(): void
    {
        $this->paystack();
        $this->fakeInitialize();
        $this->subscription->forceFill(['status' => Subscription::STATUS_EXPIRED, 'ends_at' => now()->subDays(3)])->save();

        Livewire::test(SubscriptionManager::class)->call('renew')->assertRedirect('https://checkout.paystack.com/abc123');
        $payment = \App\Models\SubscriptionPayment::sole();
        $this->signedWebhook(['event' => 'charge.success', 'data' => $this->charge($payment)])->assertOk();

        $this->assertTrue($this->tenant->fresh()->hasActiveSubscription());
        $this->assertTrue($this->subscription->fresh()->ends_at->isAfter(now()->addDays(27)));
    }

    public function test_c1_new_plan_starts_today_with_unused_time_credited(): void
    {
        $this->paystack();
        $this->fakeInitialize();
        // 15 days left of a 10,000/month plan = 5,000 of value
        $this->subscription->update(['billing_cycle' => 'monthly', 'amount' => 10000, 'ends_at' => now()->addDays(15)]);
        $bigger = \App\Models\Plan::factory()->create(['monthly_price' => 30000, 'annual_price' => 300000]);

        Livewire::test(SubscriptionManager::class)
            ->set('selectedPlanId', $bigger->id)->set('selectedBillingCycle', 'monthly')->call('changePlan');
        $payment = \App\Models\SubscriptionPayment::sole();
        $this->signedWebhook(['event' => 'charge.success', 'data' => $this->charge($payment)])->assertOk();

        $tenant = $this->tenant->fresh();
        $this->assertSame($bigger->id, $tenant->currentPlan()->id);
        // 5,000 at 1,000/day on the new plan = 4 extra days (rounded down)
        $days = now()->diffInDays($tenant->activeSubscription->ends_at);
        $this->assertTrue($days > now()->diffInDays(now()->addMonth()) + 3 && $days < now()->diffInDays(now()->addMonth()) + 6);
        $this->assertSame(Subscription::STATUS_EXPIRED, $this->subscription->fresh()->status);
    }

    public function test_c1_without_paystack_keys_the_page_says_so(): void
    {
        config(['services.paystack.secret_key' => null]);
        $this->makePending();

        Livewire::test(SubscriptionManager::class)
            ->call('payPending')
            ->assertNoRedirect()
            ->assertSee('Online payment is not set up yet');

        $this->assertFalse($this->tenant->fresh()->hasActiveSubscription());
    }

    public function test_c1_admin_can_extend_a_lapsed_subscription(): void
    {
        $this->subscription->forceFill(['status' => Subscription::STATUS_EXPIRED, 'ends_at' => now()->subDays(10)])->save();

        $controller = app(\App\Http\Controllers\Admin\AdminTenantController::class);
        $request = \App\Http\Requests\Admin\ExtendSubscriptionRequest::create('/', 'POST', ['extension_days' => 14]);
        $request->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
        $controller->extendSubscription($request, $this->tenant);

        $this->assertTrue($this->tenant->fresh()->hasActiveSubscription());
        $this->assertTrue($this->subscription->fresh()->ends_at->isAfter(now()->addDays(13)));
    }
}
