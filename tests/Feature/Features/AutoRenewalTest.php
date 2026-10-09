<?php

namespace Tests\Feature\Features;

use App\Livewire\Subscriptions\SubscriptionManager;
use App\Models\AdminUser;
use App\Models\BillingCard;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionRenewalAttempt;
use App\Models\Tenant;
use App\Notifications\AutoRenewalFailedNotification;
use App\Notifications\BillingCardExpiringNotification;
use App\Notifications\SubscriptionExpiringNotification;
use App\Notifications\SubscriptionRenewedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Session 15: Paystack auto-renewal with a saved card.
 */
class AutoRenewalTest extends TestCase
{
    private const SECRET = 'sk_test_autorenew';

    private const AUTH_CODE = 'AUTH_8dfhjjdt0e';

    /** What the fake charge_authorization endpoint answers next. */
    private ?\Closure $chargeReply = null;

    private ?\Closure $verifyReply = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-06 06:00:00'));
        config([
            'services.paystack.secret_key' => self::SECRET,
            'services.paystack.currency' => 'NGN',
            'mybooks.features.auto_renewal' => true,
            'mybooks.billing.charge_days_before' => 1,
            'mybooks.billing.retry_days' => [1, 3],
        ]);
        Notification::fake();

        $this->createAuthenticatedUser(['manage subscription', 'view dashboard']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user->assignRole('admin');

        $this->plan->update(['monthly_price' => 5000, 'annual_price' => 50000]);
        $this->subscription->update(['billing_cycle' => 'monthly', 'amount' => 5000, 'ends_at' => now()->addDays(20)]);

        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/transaction/charge_authorization' => fn (Request $r) => ($this->chargeReply ?? fn () => $this->chargeResponse('success', 'Approved'))($r),
            'api.paystack.co/transaction/verify/*' => fn (Request $r) => ($this->verifyReply)($r),
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']]),
        ]);
    }

    // ---- helpers ----------------------------------------------------------

    private function authorization(array $overrides = []): array
    {
        return array_merge([
            'authorization_code' => self::AUTH_CODE, 'bin' => '408408', 'last4' => '4081',
            'exp_month' => '08', 'exp_year' => '2027', 'channel' => 'card', 'card_type' => 'visa ',
            'bank' => 'TEST BANK', 'reusable' => true, 'signature' => 'SIG_yEXu7dLBeqG0kU7g95Ke',
        ], $overrides);
    }

    private function chargeResponse(string $status, string $message)
    {
        return function (Request $r) use ($status, $message) {
            return Http::response(['status' => true, 'message' => 'Charge attempted', 'data' => [
                'status' => $status, 'reference' => $r['reference'], 'amount' => $r['amount'], 'currency' => 'NGN',
                'gateway_response' => $message, 'paid_at' => now()->toIso8601String(),
                'authorization' => $this->authorization(),
                'customer' => ['email' => $r['email'], 'customer_code' => 'CUS_abc'],
            ]]);
        };
    }

    private function signedWebhook(array $event, string $secret = self::SECRET)
    {
        $body = json_encode($event);

        return $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, $secret),
        ], $body);
    }

    /** Pay through checkout and have Paystack confirm it by webhook. */
    private function payByCheckout(array $authorization = []): SubscriptionPayment
    {
        Livewire::test(SubscriptionManager::class)->call('renew');
        $payment = SubscriptionPayment::latest('id')->firstOrFail();

        $this->signedWebhook(['event' => 'charge.success', 'data' => [
            'status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->amountInKobo(),
            'currency' => 'NGN', 'paid_at' => now()->toIso8601String(), 'gateway_response' => 'Approved',
            'authorization' => $this->authorization($authorization),
            'customer' => ['email' => $this->user->email, 'customer_code' => 'CUS_abc'],
        ]])->assertOk();

        return $payment->fresh();
    }

    private function saveCard(Tenant $tenant, array $attrs = []): BillingCard
    {
        $card = new BillingCard(array_merge([
            'tenant_id' => $tenant->id, 'authorization_code' => self::AUTH_CODE, 'signature' => 'SIG_x',
            'card_type' => 'visa', 'bank' => 'TEST BANK', 'last4' => '4081', 'exp_month' => '08', 'exp_year' => '2027',
            'email' => 'owner@example.test', 'auto_renew' => true,
        ], $attrs));
        $card->skipTenantGuard = true;
        $card->save();

        return $card;
    }

    /** Another business (made while logged out so its set-up isn't ours). */
    private function otherTenant(): array
    {
        auth()->logout();
        $made = $this->createTenantWithSubscription();
        $this->actingAs($this->user);

        return $made;
    }

    private function endsTomorrow(?Subscription $subscription = null): Subscription
    {
        $subscription ??= $this->subscription;
        $subscription->forceFill(['ends_at' => now()->addDay()->setTime(12, 0)])->save();

        return $subscription->fresh();
    }

    private function chargesSent(): int
    {
        return count(Http::recorded(fn (Request $r) => str_contains($r->url(), 'charge_authorization')));
    }

    // ---- saving the card --------------------------------------------------

    public function test_reusable_card_is_saved_after_checkout_and_encrypted_at_rest(): void
    {
        $this->payByCheckout();

        $card = BillingCard::sole();
        $this->assertSame($this->tenant->id, $card->tenant_id);
        $this->assertSame(self::AUTH_CODE, $card->authorization_code);
        $this->assertSame('4081', $card->last4);
        $this->assertSame('Visa •••• 4081', $card->label());
        $this->assertSame('08/27', $card->expiryLabel());
        $this->assertTrue($card->auto_renew);

        $raw = DB::table('billing_cards')->first();
        $this->assertNotSame(self::AUTH_CODE, $raw->authorization_code);
        $this->assertStringNotContainsString(self::AUTH_CODE, $raw->authorization_code);
        $this->assertStringNotContainsString('CUS_abc', (string) $raw->customer_code);
        $this->assertArrayNotHasKey('authorization_code', $card->toArray());

        // Not kept in the payment's stored gateway reply either
        $this->assertStringNotContainsString(self::AUTH_CODE, json_encode(SubscriptionPayment::sole()->gateway_response));
    }

    public function test_card_is_not_saved_when_paystack_says_it_is_not_reusable(): void
    {
        $this->payByCheckout(['reusable' => false]);

        $this->assertSame(0, BillingCard::count());
        $this->assertTrue($this->tenant->fresh()->hasActiveSubscription());
    }

    public function test_paying_with_a_new_card_replaces_the_old_one_and_keeps_the_choice(): void
    {
        $this->saveCard($this->tenant, ['last4' => '1111', 'auto_renew' => false]);

        $this->payByCheckout(['last4' => '4081', 'authorization_code' => 'AUTH_newcard']);

        $card = BillingCard::sole();
        $this->assertSame('4081', $card->last4);
        $this->assertSame('AUTH_newcard', $card->authorization_code);
        $this->assertFalse($card->auto_renew);
    }

    // ---- billing page -----------------------------------------------------

    public function test_billing_page_shows_the_card_toggle_and_next_charge(): void
    {
        $this->saveCard($this->tenant);

        $this->get(route('settings.subscription'))
            ->assertOk()
            ->assertSee('Renew automatically with Visa •••• 4081, expires 08/27')
            ->assertSee('Next charge: ₦5,000.00 on '.$this->subscription->ends_at->copy()->subDay()->format('M d, Y'))
            ->assertSee('Remove card');
    }

    public function test_only_users_with_the_billing_permission_can_toggle_or_remove_the_card(): void
    {
        $this->saveCard($this->tenant);
        $clerk = $this->createUserForTenant($this->tenant, ['view dashboard']);
        $this->actingAs($clerk);

        Livewire::test(SubscriptionManager::class)->call('toggleAutoRenew')->assertForbidden();
        Livewire::test(SubscriptionManager::class)->call('removeCard')->assertForbidden();
        $this->get(route('settings.subscription'))->assertOk()->assertDontSee('4081');

        $this->assertTrue(BillingCard::sole()->auto_renew);
    }

    public function test_owner_can_switch_auto_renewal_off_and_on(): void
    {
        $this->saveCard($this->tenant);

        Livewire::test(SubscriptionManager::class)->call('toggleAutoRenew')->assertSee('Automatic renewal is off');
        $this->assertFalse(BillingCard::sole()->auto_renew);

        Livewire::test(SubscriptionManager::class)->call('toggleAutoRenew');
        $this->assertTrue(BillingCard::sole()->auto_renew);
    }

    // ---- the renewal charge -----------------------------------------------

    public function test_command_charges_only_due_subscriptions_and_only_once(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();

        // Not due yet
        [$later] = $this->otherTenant();
        $this->saveCard($later);
        // Due but switched off
        [$off, , $offSub] = $this->otherTenant();
        $this->saveCard($off, ['auto_renew' => false]);
        $this->endsTomorrow($offSub);
        // Due but cancelled
        [$cancelled, , $cancelledSub] = $this->otherTenant();
        $this->saveCard($cancelled);
        $this->endsTomorrow($cancelledSub)->cancel('Closing');
        // Due but no card
        [, , $noCardSub] = $this->otherTenant();
        $this->endsTomorrow($noCardSub);

        $this->artisan('subscriptions:auto-renew')->assertSuccessful();
        $this->artisan('subscriptions:auto-renew')->assertSuccessful();

        $this->assertSame(1, $this->chargesSent());
        $this->assertSame(1, SubscriptionRenewalAttempt::withoutGlobalScopes()->count());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'charge_authorization')
            && $r['authorization_code'] === self::AUTH_CODE
            && $r['email'] === 'owner@example.test'
            && $r['amount'] === 500000
            && str_starts_with($r['reference'], 'MB-AR-'.$this->tenant->id.'-'));
    }

    public function test_successful_renewal_extends_by_the_cycle_records_the_payment_and_emails_a_receipt(): void
    {
        $this->saveCard($this->tenant);
        $oldEnd = $this->endsTomorrow()->ends_at;

        $this->artisan('subscriptions:auto-renew')->assertSuccessful();

        $this->assertEquals($oldEnd->copy()->addMonth(), $this->subscription->fresh()->ends_at);
        $payment = SubscriptionPayment::sole();
        $this->assertSame('success', $payment->status);
        $this->assertSame('5000.00', (string) $payment->amount);
        $attempt = SubscriptionRenewalAttempt::sole();
        $this->assertSame('success', $attempt->status);
        $this->assertSame($payment->id, $attempt->subscription_payment_id);
        Notification::assertSentToTimes($this->user, SubscriptionRenewedNotification::class, 1);

        // Next day: the new period is a month away, so nothing more is charged
        $this->travel(1)->days();
        $this->artisan('subscriptions:auto-renew')->assertSuccessful();
        $this->assertSame(1, $this->chargesSent());
    }

    public function test_annual_subscription_is_charged_the_annual_price_for_a_year(): void
    {
        $this->saveCard($this->tenant);
        $this->subscription->update(['billing_cycle' => 'annual', 'amount' => 50000]);
        $oldEnd = $this->endsTomorrow()->ends_at;

        $this->artisan('subscriptions:auto-renew')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'charge_authorization') && $r['amount'] === 5000000);
        $this->assertEquals($oldEnd->copy()->addYear(), $this->subscription->fresh()->ends_at);
    }

    // ---- failures and retries ---------------------------------------------

    public function test_failure_is_recorded_retried_after_one_and_three_days_then_falls_back_to_expiry(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->chargeReply = $this->chargeResponse('failed', 'Insufficient Funds');

        // Day 0 (the day before the end)
        $this->artisan('subscriptions:auto-renew')->assertSuccessful();
        $first = SubscriptionRenewalAttempt::sole();
        $this->assertSame('failed', $first->status);
        $this->assertSame('Insufficient Funds', $first->message);
        $this->assertEquals(Carbon::parse('2026-10-07 00:00:00'), $first->next_retry_at);
        $this->assertSame('failed', SubscriptionPayment::sole()->status);
        Notification::assertSentTo($this->user, AutoRenewalFailedNotification::class, fn ($n) => ! $n->final);
        $this->artisan('subscriptions:auto-renew');
        $this->assertSame(1, $this->chargesSent());

        // Day 1: subscription ends at noon; retry at 06:00 fails
        $this->travelTo(Carbon::parse('2026-10-07 06:00:00'));
        $this->artisan('subscriptions:auto-renew');
        $this->assertSame(2, $this->chargesSent());
        $this->travelTo(Carbon::parse('2026-10-08 00:10:00'));
        $this->artisan('subscriptions:expire');
        $this->assertFalse($this->tenant->fresh()->hasActiveSubscription());

        // Day 2: nothing
        $this->travelTo(Carbon::parse('2026-10-08 06:00:00'));
        $this->artisan('subscriptions:auto-renew');
        $this->assertSame(2, $this->chargesSent());

        // Day 3: last retry, then stop
        $this->travelTo(Carbon::parse('2026-10-09 06:00:00'));
        $this->artisan('subscriptions:auto-renew');
        $this->assertSame(3, $this->chargesSent());
        $last = SubscriptionRenewalAttempt::orderByDesc('attempt')->first();
        $this->assertSame(3, $last->attempt);
        $this->assertNull($last->next_retry_at);
        Notification::assertSentTo($this->user, AutoRenewalFailedNotification::class, fn ($n) => $n->final);
        Notification::assertSentToTimes($this->user, AutoRenewalFailedNotification::class, 2);

        foreach (range(10, 14) as $day) {
            $this->travelTo(Carbon::parse("2026-10-{$day} 06:00:00"));
            $this->artisan('subscriptions:auto-renew');
        }
        $this->assertSame(3, $this->chargesSent());
        $this->assertSame(Subscription::STATUS_EXPIRED, $this->subscription->fresh()->status);
    }

    public function test_a_retry_that_succeeds_after_the_end_date_brings_the_subscription_back(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->chargeReply = $this->chargeResponse('failed', 'Declined');
        $this->artisan('subscriptions:auto-renew');

        $this->travelTo(Carbon::parse('2026-10-08 00:10:00'));
        $this->artisan('subscriptions:expire');
        $this->assertFalse($this->tenant->fresh()->hasActiveSubscription());
        // The day-1 retry was due on the 7th; it runs now, on the 8th, and succeeds
        $this->chargeReply = null;
        $this->travelTo(Carbon::parse('2026-10-08 06:00:00'));
        $this->artisan('subscriptions:auto-renew');

        $this->assertTrue($this->tenant->fresh()->hasActiveSubscription());
        $this->assertTrue($this->subscription->fresh()->ends_at->isAfter(now()->addDays(27)));
        Notification::assertSentToTimes($this->user, SubscriptionRenewedNotification::class, 1);
    }

    public function test_paying_manually_after_a_failure_stops_the_retries(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->chargeReply = $this->chargeResponse('failed', 'Declined');
        $this->artisan('subscriptions:auto-renew');

        $this->payByCheckout();   // extends ends_at, so the failed period is settled

        $this->travelTo(Carbon::parse('2026-10-07 06:00:00'));
        $this->artisan('subscriptions:auto-renew');
        $this->assertSame(1, $this->chargesSent());
    }

    public function test_send_otp_counts_as_a_failure_and_asks_for_manual_payment(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->chargeReply = $this->chargeResponse('send_otp', 'Kindly enter the OTP sent to your phone');

        $this->artisan('subscriptions:auto-renew');

        $attempt = SubscriptionRenewalAttempt::sole();
        $this->assertSame('failed', $attempt->status);
        $this->assertStringContainsString('Please pay manually', $attempt->message);
        $this->assertNull($attempt->next_retry_at);
        Notification::assertSentTo($this->user, AutoRenewalFailedNotification::class, fn ($n) => $n->final);
        $this->assertNotEquals(now()->addDay()->addMonth()->toDateString(), $this->subscription->fresh()->ends_at->toDateString());
    }

    public function test_an_expired_card_is_not_sent_to_paystack(): void
    {
        $this->saveCard($this->tenant, ['exp_month' => '09', 'exp_year' => '2026']);
        $this->endsTomorrow();

        $this->artisan('subscriptions:auto-renew');

        $this->assertSame(0, $this->chargesSent());
        $this->assertStringContainsString('expired', SubscriptionRenewalAttempt::sole()->message);
    }

    // ---- webhooks ---------------------------------------------------------

    public function test_webhook_with_a_bad_signature_is_rejected(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->chargeReply = fn (Request $r) => Http::failedConnection()($r);
        $this->artisan('subscriptions:auto-renew');
        $attempt = SubscriptionRenewalAttempt::sole();

        $event = ['event' => 'charge.success', 'data' => [
            'status' => 'success', 'reference' => $attempt->reference, 'amount' => 500000, 'currency' => 'NGN',
        ]];
        $this->signedWebhook($event, 'sk_wrong')->assertStatus(401);

        $this->assertSame('pending', $attempt->fresh()->status);
    }

    public function test_webhook_completes_a_renewal_whose_reply_was_lost_exactly_once(): void
    {
        $this->saveCard($this->tenant);
        $oldEnd = $this->endsTomorrow()->ends_at;
        $this->chargeReply = fn (Request $r) => Http::failedConnection()($r);

        $this->artisan('subscriptions:auto-renew')->assertSuccessful();
        $attempt = SubscriptionRenewalAttempt::sole();
        $this->assertSame('pending', $attempt->status);
        $this->assertEquals($oldEnd, $this->subscription->fresh()->ends_at);

        auth()->logout();
        $event = ['event' => 'charge.success', 'data' => [
            'status' => 'success', 'reference' => $attempt->reference, 'amount' => 500000, 'currency' => 'NGN',
            'gateway_response' => 'Approved', 'paid_at' => now()->toIso8601String(),
        ]];
        $this->signedWebhook($event)->assertOk();
        $this->signedWebhook($event)->assertOk();

        $this->assertEquals($oldEnd->copy()->addMonth(), $this->subscription->fresh()->ends_at);
        $this->assertSame('success', $attempt->fresh()->status);
        Notification::assertSentToTimes($this->user, SubscriptionRenewedNotification::class, 1);

        // And the next run doesn't charge the period again
        $this->chargeReply = null;
        $this->artisan('subscriptions:auto-renew');
        $this->assertSame(1, $this->chargesSent());
    }

    public function test_a_pending_renewal_is_checked_with_paystack_on_the_next_run(): void
    {
        $this->saveCard($this->tenant);
        $oldEnd = $this->endsTomorrow()->ends_at;
        $this->chargeReply = fn (Request $r) => Http::failedConnection()($r);
        $this->artisan('subscriptions:auto-renew');
        $attempt = SubscriptionRenewalAttempt::sole();

        $this->verifyReply = fn (Request $r) => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'reference' => $attempt->reference, 'amount' => 500000, 'currency' => 'NGN',
        ]]);
        $this->travel(1)->hours();
        $this->artisan('subscriptions:auto-renew');

        $this->assertSame('success', $attempt->fresh()->status);
        $this->assertEquals($oldEnd->copy()->addMonth(), $this->subscription->fresh()->ends_at);
        $this->assertSame(1, $this->chargesSent());
    }

    // ---- card expiry warning ----------------------------------------------

    public function test_owner_is_warned_once_when_the_card_expires_before_the_next_renewal(): void
    {
        // Card runs out end of October; renewal falls in November
        $card = $this->saveCard($this->tenant, ['exp_month' => '10', 'exp_year' => '2026']);
        $this->subscription->update(['ends_at' => Carbon::parse('2026-11-15 12:00:00')]);

        $this->travelTo(Carbon::parse('2026-10-23 06:00:00'));
        $this->artisan('subscriptions:auto-renew');
        Notification::assertNothingSentTo($this->user);

        $this->travelTo(Carbon::parse('2026-10-24 06:00:00'));
        $this->artisan('subscriptions:auto-renew');
        $this->travelTo(Carbon::parse('2026-10-25 06:00:00'));
        $this->artisan('subscriptions:auto-renew');

        Notification::assertSentToTimes($this->user, BillingCardExpiringNotification::class, 1);
        $this->assertSame('2026-10', $card->fresh()->expiry_warned_for);
    }

    public function test_no_card_warning_when_the_renewal_comes_before_the_card_expires(): void
    {
        $this->saveCard($this->tenant, ['exp_month' => '10', 'exp_year' => '2026']);
        $this->subscription->update(['ends_at' => Carbon::parse('2026-10-29 12:00:00')]);

        $this->travelTo(Carbon::parse('2026-10-25 06:00:00'));
        $this->artisan('subscriptions:auto-renew');

        Notification::assertNotSentTo($this->user, BillingCardExpiringNotification::class);
    }

    // ---- remove, isolation, flag ------------------------------------------

    public function test_removing_the_card_stops_renewals_and_pending_retries(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->chargeReply = $this->chargeResponse('failed', 'Declined');
        $this->artisan('subscriptions:auto-renew');

        Livewire::test(SubscriptionManager::class)->call('removeCard')->assertSee('Automatic renewal is off');

        $this->assertSame(0, BillingCard::count());
        $this->assertNull(SubscriptionRenewalAttempt::sole()->next_retry_at);
        $this->travelTo(Carbon::parse('2026-10-07 06:00:00'));
        $this->artisan('subscriptions:auto-renew');
        $this->assertSame(1, $this->chargesSent());
    }

    public function test_a_business_never_sees_or_changes_another_business_card(): void
    {
        [$other, , $otherSub] = $this->otherTenant();
        $otherCard = $this->saveCard($other, ['last4' => '9999']);
        $this->endsTomorrow($otherSub);
        $this->chargeReply = $this->chargeResponse('failed', 'Do not honour');
        $this->artisan('subscriptions:auto-renew');

        $this->get(route('settings.subscription'))->assertOk()
            ->assertDontSee('9999')->assertDontSee('Do not honour')->assertSee('No card saved');
        Livewire::test(SubscriptionManager::class)->call('toggleAutoRenew')->call('removeCard');

        $this->assertTrue($otherCard->fresh()->auto_renew);
        $this->assertSame(0, BillingCard::count());   // tenant scope: none of ours
        $this->assertSame(1, BillingCard::withoutGlobalScopes()->count());
        $this->assertSame(0, SubscriptionRenewalAttempt::count());
        $this->assertNotNull(SubscriptionRenewalAttempt::withoutGlobalScopes()->sole()->next_retry_at);
    }

    public function test_with_the_feature_off_nothing_is_saved_charged_or_shown(): void
    {
        config(['mybooks.features.auto_renewal' => false]);
        $this->payByCheckout();
        $this->assertSame(0, BillingCard::count());

        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->artisan('subscriptions:auto-renew')->expectsOutputToContain('switched off')->assertSuccessful();

        $this->assertSame(0, $this->chargesSent());
        $this->get(route('settings.subscription'))->assertOk()->assertDontSee('Card and automatic renewal');
    }

    public function test_renewal_reminders_are_not_sent_when_the_card_will_be_charged(): void
    {
        $this->saveCard($this->tenant);
        $this->subscription->update(['ends_at' => now()->addDays(7)->setTime(12, 0)]);

        $this->artisan('subscriptions:expire');
        Notification::assertNotSentTo($this->user, SubscriptionExpiringNotification::class);

        BillingCard::sole()->update(['auto_renew' => false]);
        $this->artisan('subscriptions:expire');
        Notification::assertSentTo($this->user, SubscriptionExpiringNotification::class);
    }

    public function test_platform_admin_sees_auto_renewal_status_and_last_attempt(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->chargeReply = $this->chargeResponse('failed', 'Do not honour');
        $this->artisan('subscriptions:auto-renew');

        $admin = AdminUser::create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'Secret-123!', 'is_active' => true, 'role' => 'admin']);
        $this->actingAsPlatformAdmin($admin)->get(route('admin.tenants.show', $this->tenant))
            ->assertOk()
            ->assertSee('Automatic Renewal')
            ->assertSee('Visa •••• 4081, expires 08/27')
            ->assertSee('Do not honour')
            ->assertDontSee(self::AUTH_CODE);
    }

    public function test_command_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('subscriptions:auto-renew')->assertSuccessful();
    }

    public function test_free_plans_are_never_charged(): void
    {
        $this->saveCard($this->tenant);
        $this->plan->update(['monthly_price' => 0]);
        $this->endsTomorrow();

        $this->artisan('subscriptions:auto-renew');

        $this->assertSame(0, $this->chargesSent());
        $this->assertSame(0, SubscriptionRenewalAttempt::withoutGlobalScopes()->count());
    }

    public function test_the_database_refuses_a_second_attempt_row_for_the_same_period(): void
    {
        $this->saveCard($this->tenant);
        $this->endsTomorrow();
        $this->chargeReply = $this->chargeResponse('failed', 'Declined');
        $this->artisan('subscriptions:auto-renew');
        $first = SubscriptionRenewalAttempt::sole();

        $this->expectException(UniqueConstraintViolationException::class);
        $copy = $first->replicate(['reference']);
        $copy->reference = 'MB-AR-copy';
        $copy->save();
    }

    public function test_card_codes_never_reach_the_logs(): void
    {
        $lines = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$lines) {
            $lines[] = $e->message.' '.json_encode($e->context);
        });

        $this->payByCheckout();
        $this->endsTomorrow();
        $this->chargeReply = fn (Request $r) => Http::failedConnection()($r);
        $this->artisan('subscriptions:auto-renew');

        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            $this->assertStringNotContainsString(self::AUTH_CODE, $line);
            $this->assertStringNotContainsString('CUS_abc', $line);
        }
    }
}
