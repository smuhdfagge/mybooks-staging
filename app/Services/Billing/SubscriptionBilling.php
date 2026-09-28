<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Paying for subscriptions (finding C1).
 *
 * 1. startCheckout() records a pending payment and returns the Paystack page.
 * 2. Paystack sends the customer back to the callback, and separately posts
 *    a signed webhook. Both end in applyCharge() with data that came from
 *    Paystack itself (the verify API or the signed webhook), never from the
 *    browser.
 * 3. applyCharge() checks the amount and currency, marks the payment paid
 *    once (it is safe to call twice) and starts or extends the subscription.
 */
class SubscriptionBilling
{
    /** Days used to value unused time when changing plan. */
    private const PERIOD_DAYS = [Subscription::CYCLE_MONTHLY => 30, Subscription::CYCLE_ANNUAL => 365];

    public function __construct(private PaystackGateway $gateway) {}

    public function currency(): string
    {
        return strtoupper((string) config('services.paystack.currency', 'NGN'));
    }

    /**
     * The subscription created at sign-up. It stays pending (no access)
     * until it is paid for, unless the plan is free.
     */
    public function startPendingSubscription(Tenant $tenant, Plan $plan, string $cycle): Subscription
    {
        $amount = (float) $plan->getPriceForCycle($cycle);

        $subscription = new Subscription([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'status' => Subscription::STATUS_PENDING,
            'amount' => $amount,
            'currency' => $this->currency(),
        ]);
        $subscription->save();

        if ($amount <= 0) {
            $this->activate($subscription, now());
        }

        return $subscription;
    }

    /**
     * Start paying for $plan. Returns the URL to send the customer to, or
     * null when nothing needs paying (a free plan, which is switched on
     * straight away).
     *
     * When $subscription is given (a pending sign-up, or one being renewed)
     * the payment is tied to it.
     */
    public function startCheckout(Tenant $tenant, User $user, Plan $plan, string $cycle, ?Subscription $subscription = null): ?string
    {
        if (! $plan->is_active || ! $plan->allowsBillingCycle($cycle)) {
            throw new InvalidArgumentException("The {$plan->name} plan can't be paid {$cycle}.");
        }

        $amount = round((float) $plan->getPriceForCycle($cycle), 2);

        if ($amount <= 0) {
            $this->switchToFreePlan($tenant, $plan, $cycle, $subscription);

            return null;
        }

        $payment = new SubscriptionPayment([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription?->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'billing_cycle' => $cycle,
            'reference' => 'MB-'.$tenant->id.'-'.Str::upper(Str::random(12)),
            'amount' => $amount,
            'currency' => $this->currency(),
            'gateway' => 'paystack',
        ]);
        $payment->save();

        return $this->gateway->initialize(
            $user->email,
            $payment->amountInKobo(),
            $payment->currency,
            $payment->reference,
            route('billing.callback'),
            ['tenant_id' => $tenant->id, 'plan' => $plan->name, 'billing_cycle' => $cycle],
        );
    }

    /**
     * Confirm a payment by asking Paystack (used by the browser callback).
     */
    public function verifyAndApply(string $reference): ?SubscriptionPayment
    {
        return $this->applyCharge($this->gateway->verify($reference));
    }

    /**
     * Apply a charge that Paystack reported as successful. $data must come
     * from Paystack (verify API or signed webhook). Returns the payment, or
     * null if the reference isn't one of ours.
     */
    public function applyCharge(array $data): ?SubscriptionPayment
    {
        $reference = (string) ($data['reference'] ?? '');
        if ($reference === '') {
            return null;
        }

        return DB::transaction(function () use ($reference, $data) {
            $payment = SubscriptionPayment::withoutGlobalScopes()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('Paystack charge for unknown reference', ['reference' => $reference]);

                return null;
            }

            // Already handled (webhook and callback both arrive)
            if ($payment->status === SubscriptionPayment::STATUS_SUCCESS) {
                return $payment;
            }

            $paidKobo = (int) ($data['amount'] ?? 0);
            $currency = strtoupper((string) ($data['currency'] ?? ''));
            $succeeded = ($data['status'] ?? null) === 'success';

            if (! $succeeded || $paidKobo < $payment->amountInKobo() || $currency !== $payment->currency) {
                $payment->forceFill([
                    'status' => $succeeded ? SubscriptionPayment::STATUS_PENDING : SubscriptionPayment::STATUS_FAILED,
                    'gateway_response' => $this->trim($data),
                ])->save();

                if ($succeeded) {
                    Log::error('Paystack charge does not match the checkout', [
                        'reference' => $reference, 'expected_kobo' => $payment->amountInKobo(), 'paid_kobo' => $paidKobo,
                        'expected_currency' => $payment->currency, 'currency' => $currency,
                    ]);
                }

                return $payment;
            }

            $payment->forceFill([
                'status' => SubscriptionPayment::STATUS_SUCCESS,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'gateway_response' => $this->trim($data),
            ])->save();

            $subscription = $this->applyPayment($payment);
            $payment->forceFill(['subscription_id' => $subscription->id])->save();

            return $payment;
        });
    }

    /**
     * Start or extend the tenant's subscription for a successful payment.
     */
    private function applyPayment(SubscriptionPayment $payment): Subscription
    {
        $tenant = Tenant::findOrFail($payment->tenant_id);
        $current = $this->runningSubscription($tenant);
        $linked = $payment->subscription_id
            ? Subscription::withoutGlobalScopes()->lockForUpdate()->find($payment->subscription_id)
            : null;

        // Renewing the same plan: add a period to whatever is left
        if ($current && (int) $current->plan_id === (int) $payment->plan_id && $current->billing_cycle === $payment->billing_cycle) {
            $from = $current->ends_at && $current->ends_at->isFuture() ? $current->ends_at : now();
            $current->forceFill([
                'ends_at' => $this->periodEnd($from, $payment->billing_cycle),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'payment_reference' => $payment->reference,
            ])->save();
            $this->closePending($tenant, $current);

            return $current;
        }

        // A new plan (or no running subscription): start it today, with the
        // unused value of the current plan added as extra days.
        $extraDays = $current ? $this->unusedDays($current, (float) $payment->amount, $payment->billing_cycle) : 0;

        $subscription = $linked && in_array($linked->status, [Subscription::STATUS_PENDING, Subscription::STATUS_EXPIRED], true)
            ? $linked
            : new Subscription(['tenant_id' => $tenant->id]);

        $subscription->forceFill([
            'plan_id' => $payment->plan_id,
            'billing_cycle' => $payment->billing_cycle,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'payment_reference' => $payment->reference,
        ]);
        $this->activate($subscription, now(), $extraDays);

        if ($current && $current->id !== $subscription->id) {
            $current->forceFill([
                'status' => Subscription::STATUS_EXPIRED,
                'ends_at' => now(),
                'cancellation_reason' => 'Changed to '.($subscription->plan?->name ?? 'another plan'),
            ])->save();
        }
        $this->closePending($tenant, $subscription);

        return $subscription;
    }

    private function switchToFreePlan(Tenant $tenant, Plan $plan, string $cycle, ?Subscription $subscription): void
    {
        DB::transaction(function () use ($tenant, $plan, $cycle, $subscription) {
            $current = $this->runningSubscription($tenant);
            $target = $subscription && $subscription->status === Subscription::STATUS_PENDING
                ? $subscription
                : new Subscription(['tenant_id' => $tenant->id]);

            $target->forceFill([
                'plan_id' => $plan->id, 'billing_cycle' => $cycle, 'amount' => 0, 'currency' => $this->currency(),
            ]);
            $this->activate($target, now());

            if ($current && $current->id !== $target->id) {
                $current->forceFill(['status' => Subscription::STATUS_EXPIRED, 'ends_at' => now(), 'cancellation_reason' => "Changed to {$plan->name}"])->save();
            }
            $this->closePending($tenant, $target);
        });
    }

    private function activate(Subscription $subscription, Carbon $from, int $extraDays = 0): void
    {
        $subscription->forceFill([
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $from,
            'ends_at' => $this->periodEnd($from, $subscription->billing_cycle)->addDays($extraDays),
        ])->save();
    }

    private function periodEnd(Carbon $from, string $cycle): Carbon
    {
        return $cycle === Subscription::CYCLE_ANNUAL ? $from->copy()->addYear() : $from->copy()->addMonth();
    }

    /**
     * Value of the time left on $current, as days of the new plan.
     */
    private function unusedDays(Subscription $current, float $newAmount, string $newCycle): int
    {
        if (! $current->ends_at || ! $current->ends_at->isFuture() || (float) $current->amount <= 0 || $newAmount <= 0) {
            return 0;
        }

        $daysLeft = now()->diffInSeconds($current->ends_at) / 86400;
        $credit = $daysLeft * ((float) $current->amount / (self::PERIOD_DAYS[$current->billing_cycle] ?? 30));
        $newDaily = $newAmount / (self::PERIOD_DAYS[$newCycle] ?? 30);

        return (int) floor($credit / $newDaily);
    }

    private function runningSubscription(Tenant $tenant): ?Subscription
    {
        return Subscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('ends_at')->latest('id')
            ->lockForUpdate()
            ->first();
    }

    /** Pending sign-up subscriptions are no longer needed once one is paid. */
    private function closePending(Tenant $tenant, Subscription $keep): void
    {
        Subscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', Subscription::STATUS_PENDING)
            ->where('id', '!=', $keep->id)
            ->update(['status' => Subscription::STATUS_EXPIRED, 'cancellation_reason' => 'Replaced by a paid subscription']);
    }

    /** Keep what's useful from Paystack's reply, without card details. */
    private function trim(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'id', 'status', 'reference', 'amount', 'currency', 'paid_at', 'channel', 'gateway_response', 'fees',
        ]));
    }
}
