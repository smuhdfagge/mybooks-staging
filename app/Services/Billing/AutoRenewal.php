<?php

namespace App\Services\Billing;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\BillingCard;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionRenewalAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AutoRenewalFailedNotification;
use App\Notifications\BillingCardExpiringNotification;
use App\Notifications\SubscriptionRenewedNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Renewing subscriptions with the saved card (session 15).
 *
 * Each subscription period is charged at most once: an attempt row is
 * written (unique per subscription, period end and attempt number) under a
 * lock on the subscription before Paystack is called, and a period that
 * already has a pending or successful attempt is never charged again. A
 * successful charge goes through SubscriptionBilling::applyCharge(), the
 * same code a manual payment uses, so the subscription is extended the same
 * way.
 */
class AutoRenewal
{
    /** Paystack replies that mean the bank wants the customer to act. */
    private const NEEDS_CUSTOMER = ['send_otp', 'send_pin', 'send_birthday', 'send_phone', 'send_address', 'open_url', 'pending', 'paused', 'ongoing'];

    public function __construct(private PaystackGateway $gateway, private SubscriptionBilling $billing) {}

    public function enabled(): bool
    {
        return EnsureFeatureEnabled::enabled('auto_renewal');
    }

    /** @return array<int, int> */
    private function retryDays(): array
    {
        return array_values((array) config('mybooks.billing.retry_days', [1, 3]));
    }

    private function daysBefore(): int
    {
        return (int) config('mybooks.billing.charge_days_before', 1);
    }

    /**
     * The daily run. Returns counts for the command's output.
     *
     * @return array{charged: int, failed: int, pending: int, warned: int}
     */
    public function run(): array
    {
        $stats = ['charged' => 0, 'failed' => 0, 'pending' => 0, 'warned' => 0];

        if (! $this->enabled() || ! $this->gateway->isConfigured()) {
            return $stats;
        }

        $this->checkPending($stats);

        foreach ($this->dueSubscriptions() as $subscription) {
            if ($subscription->ends_at) {
                $this->tally($stats, $this->charge($subscription, $subscription->ends_at, 1));
            }
        }

        foreach ($this->dueRetries() as $previous) {
            $this->tally($stats, $this->retry($previous));
        }

        $stats['warned'] = $this->warnExpiringCards();

        return $stats;
    }

    private function tally(array &$stats, ?string $result): void
    {
        match ($result) {
            SubscriptionRenewalAttempt::STATUS_SUCCESS => $stats['charged']++,
            SubscriptionRenewalAttempt::STATUS_FAILED => $stats['failed']++,
            SubscriptionRenewalAttempt::STATUS_PENDING => $stats['pending']++,
            default => null,
        };
    }

    /**
     * Running subscriptions that end by the end of the charge day and have
     * no attempt for this period yet.
     *
     * @return Collection<int, Subscription>
     */
    public function dueSubscriptions(): Collection
    {
        $tenantIds = BillingCard::withoutGlobalScopes()->where('auto_renew', true)->pluck('tenant_id');

        return Subscription::withoutGlobalScopes()
            ->with('plan')
            ->whereIn('tenant_id', $tenantIds)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNull('cancelled_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<', now()->startOfDay()->addDays($this->daysBefore() + 1))
            ->get()
            ->filter(fn (Subscription $s) => ! SubscriptionRenewalAttempt::withoutGlobalScopes()
                ->where('subscription_id', $s->id)->where('period_end', $s->ends_at)->exists())
            ->values();
    }

    /** @return Collection<int, SubscriptionRenewalAttempt> */
    private function dueRetries(): Collection
    {
        return SubscriptionRenewalAttempt::withoutGlobalScopes()
            ->where('status', SubscriptionRenewalAttempt::STATUS_FAILED)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('id')
            ->get();
    }

    private function retry(SubscriptionRenewalAttempt $previous): ?string
    {
        $subscription = Subscription::withoutGlobalScopes()->with('plan')->find($previous->subscription_id);

        // Paid some other way, plan changed, cancelled, or card gone: stop.
        $stillDue = $subscription
            && ! $subscription->cancelled_at
            && in_array($subscription->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_EXPIRED], true)
            && $subscription->ends_at?->equalTo($previous->period_end)
            && $this->cardFor($subscription->tenant_id)?->auto_renew;

        if (! $stillDue) {
            $previous->forceFill(['next_retry_at' => null])->save();

            return null;
        }

        return $this->charge($subscription, $previous->period_end, $previous->attempt + 1, $previous);
    }

    /**
     * Charge the saved card for one period. Returns the attempt status, or
     * null when nothing was charged (already done, or not due).
     */
    public function charge(Subscription $subscription, CarbonInterface $periodEnd, int $attemptNo, ?SubscriptionRenewalAttempt $previous = null): ?string
    {
        $card = $this->cardFor($subscription->tenant_id);
        $plan = $subscription->plan;

        if (! $card || ! $card->auto_renew || ! $plan || (float) $plan->getPriceForCycle($subscription->billing_cycle) <= 0) {
            return null;
        }

        try {
            [$attempt, $payment] = DB::transaction(function () use ($subscription, $periodEnd, $attemptNo, $previous, $card, $plan) {
                $locked = Subscription::withoutGlobalScopes()->lockForUpdate()->find($subscription->id);

                if (! $locked || ! $locked->ends_at?->equalTo($periodEnd)) {
                    return [null, null];
                }

                // Never twice for one period: stop if one is paid or still open.
                $open = SubscriptionRenewalAttempt::withoutGlobalScopes()
                    ->where('subscription_id', $locked->id)
                    ->where('period_end', $periodEnd)
                    ->whereIn('status', [SubscriptionRenewalAttempt::STATUS_SUCCESS, SubscriptionRenewalAttempt::STATUS_PENDING])
                    ->exists();
                if ($open) {
                    return [null, null];
                }

                if ($previous) {
                    $claimed = SubscriptionRenewalAttempt::withoutGlobalScopes()
                        ->whereKey($previous->id)->whereNotNull('next_retry_at')
                        ->update(['next_retry_at' => null]);
                    if ($claimed === 0) {
                        return [null, null];
                    }
                }

                $reference = 'MB-AR-'.$locked->tenant_id.'-'.Str::upper(Str::random(12));
                $amount = round((float) $plan->getPriceForCycle($locked->billing_cycle), 2);

                $payment = new SubscriptionPayment([
                    'tenant_id' => $locked->tenant_id,
                    'subscription_id' => $locked->id,
                    'plan_id' => $plan->id,
                    'user_id' => null,
                    'billing_cycle' => $locked->billing_cycle,
                    'reference' => $reference,
                    'amount' => $amount,
                    'currency' => $this->billing->currency(),
                    'gateway' => 'paystack',
                ]);
                $payment->skipTenantGuard = true;
                $payment->save();

                $attempt = new SubscriptionRenewalAttempt([
                    'tenant_id' => $locked->tenant_id,
                    'subscription_id' => $locked->id,
                    'subscription_payment_id' => $payment->id,
                    'period_end' => $periodEnd,
                    'attempt' => $attemptNo,
                    'reference' => $reference,
                    'amount' => $amount,
                    'currency' => $payment->currency,
                    'card_last4' => $card->last4,
                    'status' => SubscriptionRenewalAttempt::STATUS_PENDING,
                ]);
                $attempt->skipTenantGuard = true;
                $attempt->save();

                return [$attempt, $payment];
            });
        } catch (UniqueConstraintViolationException) {
            return null;   // another run got there first
        }

        if (! $attempt || ! $payment) {
            return null;
        }

        // Checks that need no call to Paystack.
        if (! $plan->is_active || ! $plan->allowsBillingCycle($payment->billing_cycle)) {
            return $this->fail($attempt, $payment, 'The plan can no longer be renewed automatically. Please choose a plan and pay.', final: true);
        }
        if ($card->hasExpiredBy(now())) {
            return $this->fail($attempt, $payment, "Your saved card expired at the end of {$card->expiryLabel()}.", final: true);
        }

        try {
            $data = $this->gateway->chargeAuthorization(
                (string) $card->authorization_code,
                $card->email,
                $payment->amountInKobo(),
                $payment->currency,
                $payment->reference,
                ['tenant_id' => $attempt->tenant_id, 'auto_renewal' => true, 'attempt' => $attemptNo],
            );
        } catch (Throwable $e) {
            // We don't know if the card was charged. Leave it pending: the
            // webhook or the next run's check with Paystack settles it.
            Log::warning('Auto-renewal charge did not get a reply', [
                'tenant_id' => $attempt->tenant_id, 'reference' => $attempt->reference,
                'card' => BillingCard::mask($card->authorization_code), 'error' => $e->getMessage(),
            ]);
            $attempt->forceFill(['message' => 'Waiting for Paystack to confirm.'])->save();

            return SubscriptionRenewalAttempt::STATUS_PENDING;
        }

        return $this->settle($attempt, $payment, $data);
    }

    /** Act on what Paystack said about an attempt. */
    private function settle(SubscriptionRenewalAttempt $attempt, SubscriptionPayment $payment, array $data): string
    {
        $status = (string) ($data['status'] ?? 'failed');
        $data['reference'] = $attempt->reference;

        if ($status === 'success') {
            $applied = $this->billing->applyCharge($data);
            if ($applied?->status === SubscriptionPayment::STATUS_SUCCESS) {
                return SubscriptionRenewalAttempt::STATUS_SUCCESS;
            }

            return $this->fail($attempt, $payment, 'The amount charged did not match the plan price. Our team has been alerted.', final: true);
        }

        if (in_array($status, self::NEEDS_CUSTOMER, true)) {
            return $this->fail($attempt, $payment, 'Your bank asked for an extra confirmation (such as an OTP), so the card could not be charged automatically. Please pay manually.', final: true, data: $data);
        }

        $message = trim((string) ($data['gateway_response'] ?? $data['message'] ?? '')) ?: 'The card was declined.';

        return $this->fail($attempt, $payment, $message, final: false, data: $data);
    }

    private function fail(SubscriptionRenewalAttempt $attempt, SubscriptionPayment $payment, string $message, bool $final, array $data = []): string
    {
        $retryDays = $this->retryDays();
        $nextRetry = null;

        if (! $final && isset($retryDays[$attempt->attempt - 1])) {
            $first = SubscriptionRenewalAttempt::withoutGlobalScopes()
                ->where('subscription_id', $attempt->subscription_id)
                ->where('period_end', $attempt->period_end)
                ->where('attempt', 1)
                ->value('created_at');
            $nextRetry = Carbon::parse($first ?? $attempt->created_at)->startOfDay()->addDays($retryDays[$attempt->attempt - 1]);
        }

        $payment->forceFill([
            'status' => SubscriptionPayment::STATUS_FAILED,
            'gateway_response' => array_intersect_key($data, array_flip(['id', 'status', 'reference', 'amount', 'currency', 'gateway_response'])) ?: ['message' => $message],
        ])->save();

        $attempt->forceFill([
            'status' => SubscriptionRenewalAttempt::STATUS_FAILED,
            'message' => Str::limit($message, 250),
            'next_retry_at' => $nextRetry,
        ])->save();

        Log::info('Auto-renewal charge failed', [
            'tenant_id' => $attempt->tenant_id, 'reference' => $attempt->reference,
            'attempt' => $attempt->attempt, 'message' => $message, 'retry_at' => $nextRetry?->toDateString(),
        ]);

        // Email on the first failure and when we give up (once if both).
        if ($attempt->attempt === 1 || $nextRetry === null) {
            $this->notifyAdmins($attempt->tenant_id, new AutoRenewalFailedNotification($attempt, $nextRetry === null));
        }

        return SubscriptionRenewalAttempt::STATUS_FAILED;
    }

    /**
     * Attempts left pending because Paystack's reply was lost: ask Paystack
     * how they ended.
     */
    private function checkPending(array &$stats): void
    {
        $stale = SubscriptionRenewalAttempt::withoutGlobalScopes()
            ->where('status', SubscriptionRenewalAttempt::STATUS_PENDING)
            ->where('created_at', '<=', now()->subMinutes(15))
            ->get();

        foreach ($stale as $attempt) {
            $payment = SubscriptionPayment::withoutGlobalScopes()->where('reference', $attempt->reference)->first();
            if (! $payment) {
                continue;
            }

            try {
                $data = $this->gateway->verify($attempt->reference);
            } catch (Throwable $e) {
                Log::warning('Could not check a pending auto-renewal', ['reference' => $attempt->reference, 'error' => $e->getMessage()]);

                continue;
            }

            $status = (string) ($data['status'] ?? '');
            if ($status === 'success' || in_array($status, ['failed', 'abandoned', 'reversed'], true)) {
                $this->tally($stats, $this->settle($attempt, $payment, $data));
            }
        }
    }

    /**
     * Warn a business whose saved card runs out before its next renewal
     * charge, a set number of days before the card's expiry month ends.
     */
    public function warnExpiringCards(): int
    {
        $warnDays = (int) config('mybooks.billing.card_expiry_warning_days', 7);
        $sent = 0;

        foreach (BillingCard::withoutGlobalScopes()->where('auto_renew', true)->get() as $card) {
            $expires = $card->expiresAt();
            $key = $card->exp_year.'-'.$card->exp_month;

            if (! $expires || $card->expiry_warned_for === $key
                || now()->lt($expires->copy()->subDays($warnDays)->startOfDay()) || now()->gt($expires)) {
                continue;
            }

            $next = $this->nextRenewal($card->tenant_id);
            if (! $next || ! $next['charge_on']->greaterThan($expires)) {
                continue;
            }

            $this->notifyAdmins($card->tenant_id, new BillingCardExpiringNotification($card, $next['charge_on']));
            $card->forceFill(['expiry_warned_for' => $key])->save();
            $sent++;
        }

        return $sent;
    }

    /**
     * The next automatic charge for a business: date, amount and the
     * subscription, or null when nothing will be charged.
     *
     * @return array{subscription: Subscription, charge_on: CarbonInterface, amount: float, currency: string}|null
     */
    public function nextRenewal(int $tenantId): ?array
    {
        $subscription = Subscription::withoutGlobalScopes()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNull('cancelled_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->latest('ends_at')->latest('id')
            ->first();

        if (! $subscription?->plan || (float) $subscription->plan->getPriceForCycle($subscription->billing_cycle) <= 0) {
            return null;
        }

        $chargeOn = $subscription->ends_at->copy()->startOfDay()->subDays($this->daysBefore());

        return [
            'subscription' => $subscription,
            'charge_on' => $chargeOn->lessThan(now()->startOfDay()) ? now()->startOfDay() : $chargeOn,
            'amount' => round((float) $subscription->plan->getPriceForCycle($subscription->billing_cycle), 2),
            'currency' => $this->billing->currency(),
        ];
    }

    public function cardFor(int $tenantId): ?BillingCard
    {
        return BillingCard::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
    }

    /** Email the receipt for a successful automatic renewal. */
    public function sendReceipt(SubscriptionRenewalAttempt $attempt): void
    {
        $this->notifyAdmins($attempt->tenant_id, new SubscriptionRenewedNotification($attempt->fresh() ?? $attempt));
    }

    private function notifyAdmins(int $tenantId, Notification $notification): void
    {
        $admins = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->role('admin')
            ->get();

        if ($admins->isEmpty() && ($owner = Tenant::find($tenantId)?->owner())) {
            $admins = collect([$owner]);
        }

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }
}
