<?php

namespace Tests\Feature\Billing;

use App\Livewire\Auth\RegisterWizard;
use App\Livewire\Subscriptions\SubscriptionManager;
use App\Models\BillingCard;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionExpiringNotification;
use App\Services\Billing\AutoRenewal;
use App\Services\Billing\SubscriptionBilling;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A paid plan starts with a 10-day free trial and no card (owner's
 * decision, November 2026; mybooks.trial_days). The trial gives the plan in
 * full. Paying during it keeps the days left; when it runs out unpaid the
 * business is asked to pay, as for any lapsed plan, and its data stays.
 */
class FreeTrialTest extends TestCase
{
    private const SECRET = 'sk_test_trial';

    private const PASSWORD = 'Kano-Textiles-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
        config([
            'mybooks.trial_days' => 10,
            'services.paystack.secret_key' => self::SECRET,
            'services.paystack.currency' => 'NGN',
            'mybooks.features.auto_renewal' => true,
        ]);
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']]),
            'api.paystack.co/transaction/charge_authorization' => fn (Request $r) => Http::response(['status' => true, 'data' => [
                'status' => 'success', 'reference' => $r['reference'], 'amount' => $r['amount'], 'currency' => 'NGN',
            ]]),
        ]);
    }

    private function plan(array $attrs = []): Plan
    {
        return Plan::factory()->create(array_merge([
            'slug' => 'starter-'.uniqid(), 'is_active' => true,
            'monthly_price' => 25000, 'annual_price' => 250000,
            'allow_monthly_billing' => true, 'allow_annual_billing' => true,
        ], $attrs));
    }

    private function signUp(Plan $plan): User
    {
        Livewire::test(RegisterWizard::class)
            ->set('plan_id', $plan->id)
            ->set('name', 'Amina Yusuf')
            ->set('email', 'amina@kanotextiles.test')
            ->set('password', self::PASSWORD)
            ->set('company_name', 'Kano Textiles Ltd')
            ->call('register')
            ->assertHasNoErrors();

        return User::where('email', 'amina@kanotextiles.test')->firstOrFail();
    }

    /** The signed-in business, on day one of a trial of its plan. */
    private function onTrial(): Subscription
    {
        $this->createAuthenticatedUser(['manage subscription', 'view dashboard']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user->assignRole('admin');
        $this->plan->update(['monthly_price' => 5000, 'annual_price' => 50000, 'allow_monthly_billing' => true, 'allow_annual_billing' => true]);

        $this->subscription->forceFill([
            'status' => Subscription::STATUS_TRIALING, 'billing_cycle' => 'monthly', 'amount' => 5000,
            'starts_at' => now(), 'trial_ends_at' => now()->addDays(10), 'ends_at' => now()->addDays(10),
            'payment_reference' => null,
        ])->save();

        return $this->subscription;
    }

    /** Pay through checkout and have Paystack confirm it by signed webhook. */
    private function pay(callable $start): SubscriptionPayment
    {
        $start(Livewire::test(SubscriptionManager::class));
        $payment = SubscriptionPayment::latest('id')->firstOrFail();

        $body = json_encode(['event' => 'charge.success', 'data' => [
            'status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->amountInKobo(),
            'currency' => 'NGN', 'paid_at' => now()->toIso8601String(), 'gateway_response' => 'Approved',
            'customer' => ['email' => $this->user->email],
        ]]);
        $this->signedOut(fn () => $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::SECRET),
        ], $body))->assertOk();

        return $payment->fresh();
    }

    // ---- signing up --------------------------------------------------------

    public function test_signing_up_for_a_paid_plan_starts_a_ten_day_trial_with_no_payment(): void
    {
        $user = $this->signUp($this->plan());
        $user->markEmailAsVerified();

        $sub = Subscription::where('tenant_id', $user->tenant_id)->sole();
        $this->assertSame(Subscription::STATUS_TRIALING, $sub->status);
        $this->assertTrue($sub->onTrial());
        $this->assertSame('2026-11-28 10:00:00', $sub->ends_at->toDateTimeString());
        $this->assertEquals($sub->ends_at, $sub->trial_ends_at);
        $this->assertSame(0, SubscriptionPayment::count(), 'No card, no payment.');

        // The trial opens the app: no detour to the payment page.
        $tenant = Tenant::findOrFail($user->tenant_id);
        $this->assertTrue($tenant->hasActiveSubscription());
        $this->assertSame('Free trial', $tenant->subscription_status);
        $this->actingAs($user)->get(route('settings.subscription'))->assertOk()
            ->assertSee('free trial of the', false)->assertSee('No card is needed');
    }

    public function test_with_trials_off_sign_up_waits_for_payment_as_before(): void
    {
        config(['mybooks.trial_days' => 0]);

        $user = $this->signUp($this->plan());

        $sub = Subscription::where('tenant_id', $user->tenant_id)->sole();
        $this->assertSame(Subscription::STATUS_PENDING, $sub->status);
        $this->assertFalse(Tenant::findOrFail($user->tenant_id)->hasActiveSubscription());
    }

    public function test_a_free_plan_is_switched_on_with_no_trial(): void
    {
        $user = $this->signUp($this->plan(['monthly_price' => 0, 'annual_price' => 0]));

        $sub = Subscription::where('tenant_id', $user->tenant_id)->sole();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertNull($sub->trial_ends_at);
    }

    public function test_the_sign_up_form_says_free_for_ten_days_and_no_card(): void
    {
        $plan = $this->plan();

        Livewire::test(RegisterWizard::class)->set('plan_id', $plan->id)
            ->assertSee('Free for 10 days, no card needed.')
            ->assertDontSee("You'll pay after you confirm your email.", false);

        config(['mybooks.trial_days' => 0]);
        Livewire::test(RegisterWizard::class)->set('plan_id', $plan->id)
            ->assertDontSee('Free for')
            ->assertSee("You'll pay after you confirm your email.", false);
    }

    // ---- during the trial ---------------------------------------------------

    public function test_the_trial_gives_the_app_and_the_subscription_page_offers_to_pay(): void
    {
        $this->onTrial();

        $this->get(route('dashboard'))->assertOk();
        Livewire::test(SubscriptionManager::class)
            ->assertSee('Free trial')
            ->assertSee('Trial Ends')
            ->assertSee('Pay now: ₦5,000 a month')
            ->assertDontSeeHtml('wire:click="openCancelModal"'); // a trial just ends: nothing to cancel
    }

    public function test_the_dashboard_counts_down_the_trial(): void
    {
        $this->onTrial();

        $titles = array_column(app(DashboardService::class)->attention($this->user), 'title');
        $this->assertContains('Your free trial: 10 days left', $titles);

        $this->travel(9)->days();
        $titles = array_column(app(DashboardService::class)->attention($this->user), 'title');
        $this->assertContains('Your free trial ends tomorrow', $titles);
    }

    public function test_paying_for_the_same_plan_during_the_trial_keeps_the_days_left(): void
    {
        $trial = $this->onTrial();
        $this->travel(4)->days();

        $payment = $this->pay(fn ($c) => $c->call('renew'));

        $this->assertSame(SubscriptionPayment::STATUS_SUCCESS, $payment->status);
        $sub = $trial->fresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertFalse($sub->onTrial());
        // The month starts when the trial would have ended (28 Nov).
        $this->assertSame('2026-12-28', $sub->ends_at->toDateString());
        $this->assertSame($payment->reference, $sub->payment_reference);
        $this->assertSame(1, Subscription::where('tenant_id', $this->tenant->id)->count());
        $this->assertNotContains('Your free trial: 6 days left', array_column(app(DashboardService::class)->attention($this->user), 'title'));
    }

    public function test_paying_for_another_plan_during_the_trial_adds_the_days_left(): void
    {
        $trial = $this->onTrial();
        $bigger = $this->plan(['name' => 'Professional', 'monthly_price' => 12000, 'annual_price' => 120000]);
        $this->travel(4)->days(); // 6 days of trial left

        $this->pay(fn ($c) => $c->call('openUpgradeModal', $bigger->id)->set('selectedBillingCycle', 'annual')->call('changePlan'));

        $running = $this->tenant->fresh()->activeSubscription;
        $this->assertSame(Subscription::STATUS_ACTIVE, $running->status);
        $this->assertSame($bigger->id, $running->plan_id);
        // 22 Nov + one year + the 6 trial days.
        $this->assertSame('2027-11-28', $running->ends_at->toDateString());
        // The trial is closed, so the business is not "on trial" any more.
        $this->assertSame(Subscription::STATUS_EXPIRED, $trial->fresh()->status);
        $this->assertSame(0, Subscription::where('tenant_id', $this->tenant->id)->where('status', Subscription::STATUS_TRIALING)->count());
    }

    public function test_a_trial_is_never_charged_automatically(): void
    {
        $this->onTrial();
        $card = new BillingCard([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'authorization_code' => 'AUTH_x',
            'last4' => '4081', 'exp_month' => '08', 'exp_year' => '2030', 'email' => $this->user->email, 'auto_renew' => true,
        ]);
        $card->skipTenantGuard = true;
        $card->save();
        $this->travel(9)->days();

        app(AutoRenewal::class)->run();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'charge_authorization'));
        $this->assertTrue($this->subscription->fresh()->onTrial());
    }

    // ---- the end of the trial ---------------------------------------------

    public function test_reminders_three_days_and_one_day_before_the_trial_ends_once_each(): void
    {
        $this->onTrial();

        foreach ([7 => 3, 9 => 1] as $day => $left) {
            $this->travelTo(Carbon::parse('2026-11-18 10:00:00')->addDays($day)->setTime(8, 0));
            $this->artisan('subscriptions:expire')->assertSuccessful();
            $this->artisan('subscriptions:expire')->assertSuccessful();

            Notification::assertSentToTimes($this->user, SubscriptionExpiringNotification::class,
                $left === 3 ? 1 : 2);
            Notification::assertSentTo($this->user, SubscriptionExpiringNotification::class,
                fn ($n) => $n->trial && $n->daysLeft === $left
                    && str_contains($n->toMail($this->user)->subject, 'free trial ends')
                    && str_contains(implode(' ', $n->toMail($this->user)->introLines), 'The days left of your trial are kept.'));
        }
    }

    public function test_when_the_trial_runs_out_unpaid_the_business_is_asked_to_pay_and_keeps_its_data(): void
    {
        $trial = $this->onTrial();
        $this->travelTo(now()->addDays(10)->addMinute());

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(Subscription::STATUS_EXPIRED, $trial->fresh()->status);
        Notification::assertSentTo($this->user, SubscriptionExpiringNotification::class,
            fn ($n) => $n->trial && $n->daysLeft === 0 && $n->toMail($this->user)->subject === 'Your MyBooks free trial has ended');

        $this->get(route('dashboard'))->assertRedirect(route('settings.subscription'));
        Livewire::test(SubscriptionManager::class)
            ->assertSee('Your free trial has ended')
            ->assertSee('Everything you entered is safe')
            ->assertSee('Pay for '.$this->plan->name);

        // Paying now starts the month today, on the same record.
        $this->pay(fn ($c) => $c->call('renew'));
        $sub = $trial->fresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame(now()->addMonth()->toDateString(), $sub->ends_at->toDateString());
    }

    public function test_trial_days_come_from_config_and_never_go_below_zero(): void
    {
        config(['mybooks.trial_days' => 14]);
        $this->assertSame(14, SubscriptionBilling::trialDays());

        config(['mybooks.trial_days' => -3]);
        $this->assertSame(0, SubscriptionBilling::trialDays());
    }
}
