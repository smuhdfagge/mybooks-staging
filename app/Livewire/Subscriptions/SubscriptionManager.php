<?php

namespace App\Livewire\Subscriptions;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\BillingCard;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionRenewalAttempt;
use App\Services\Billing\AutoRenewal;
use App\Services\Billing\PaystackGateway;
use App\Services\Billing\SubscriptionBilling;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SubscriptionManager extends Component
{
    use ChecksPermissions;

    public $currentSubscription;

    public $pendingSubscription;   // signed up, waiting for payment

    public $lapsedSubscription;    // ended, can be renewed

    public $currentPlan;

    public $plans;

    public $selectedPlanId;

    public $selectedBillingCycle;

    public $showUpgradeModal = false;

    public $showCancelModal = false;

    public $cancellationReason = '';

    public function mount()
    {
        $tenant = Auth::user()->tenant;

        if (! $tenant) {
            $this->plans = Plan::active()->ordered()->get();

            return;
        }

        $this->currentSubscription = $tenant->activeSubscription;
        $this->currentPlan = $tenant->currentPlan();
        $this->plans = Plan::active()->ordered()->get();

        if (! $this->currentSubscription) {
            $latest = $tenant->subscriptions()->with('plan')->latest('id')->first();
            $this->pendingSubscription = $latest?->status === Subscription::STATUS_PENDING ? $latest : null;
            $this->lapsedSubscription = $latest && $latest->status !== Subscription::STATUS_PENDING ? $latest : null;
        }
    }

    public function openUpgradeModal($planId = null)
    {
        $this->selectedPlanId = $planId ?? $this->currentPlan?->id;
        $this->selectedBillingCycle = $this->currentSubscription?->billing_cycle ?? Subscription::CYCLE_MONTHLY;
        $this->showUpgradeModal = true;
    }

    public function closeUpgradeModal()
    {
        $this->showUpgradeModal = false;
        $this->reset(['selectedPlanId', 'selectedBillingCycle']);
    }

    /**
     * Pay for the plan chosen in the modal (finding C1). Nothing changes
     * until Paystack confirms the payment.
     */
    public function changePlan()
    {
        $this->requirePermission('manage subscription');

        $this->validate([
            'selectedPlanId' => 'required|exists:plans,id',
            'selectedBillingCycle' => 'required|in:monthly,annual',
        ]);

        $newPlan = Plan::findOrFail($this->selectedPlanId);

        if (! $newPlan->allowsBillingCycle($this->selectedBillingCycle)) {
            session()->flash('error', "The {$newPlan->name} plan does not support {$this->selectedBillingCycle} billing.");

            return;
        }

        return $this->checkout($newPlan, $this->selectedBillingCycle, $this->pendingSubscription);
    }

    /** Pay for the plan chosen at sign-up. */
    public function payPending()
    {
        $this->requirePermission('manage subscription');

        $pending = $this->pendingSubscription?->fresh();
        if (! $pending || $pending->status !== Subscription::STATUS_PENDING || ! $pending->plan) {
            return;
        }

        return $this->checkout($pending->plan, $pending->billing_cycle, $pending);
    }

    /** Pay for another period of the current (or last) plan. */
    public function renew()
    {
        $this->requirePermission('manage subscription');

        $subscription = ($this->currentSubscription ?? $this->lapsedSubscription)?->fresh();
        if (! $subscription || ! $subscription->plan) {
            return;
        }

        return $this->checkout($subscription->plan, $subscription->billing_cycle, $subscription);
    }

    private function checkout(Plan $plan, string $cycle, ?Subscription $subscription)
    {
        $billing = app(SubscriptionBilling::class);

        try {
            $url = $billing->startCheckout(Auth::user()->tenant, Auth::user(), $plan, $cycle, $subscription);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', app(PaystackGateway::class)->isConfigured()
                ? "We couldn't reach the payment page. Please try again in a moment."
                : 'Online payment is not set up yet. Please contact support to activate your subscription.');
            $this->closeUpgradeModal();

            return;
        }

        if ($url === null) {
            // Free plan: already switched on
            session()->flash('success', "You are now on the {$plan->name} plan.");

            return redirect()->route('settings.subscription');
        }

        return redirect()->away($url);
    }

    public function openCancelModal()
    {
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->cancellationReason = '';
    }

    public function cancelSubscription()
    {
        $this->requirePermission('manage subscription');

        $this->validate([
            'cancellationReason' => 'nullable|string|max:500',
        ]);

        if ($this->currentSubscription) {
            $this->currentSubscription->cancel($this->cancellationReason);
            $this->currentSubscription->refresh();
            session()->flash('success', 'Your subscription has been cancelled. You will have access until '.$this->currentSubscription->ends_at->format('M d, Y').'.');
        }

        $this->closeCancelModal();
    }

    public function reactivateSubscription()
    {
        $this->requirePermission('manage subscription');

        if (! $this->currentSubscription || ! $this->currentSubscription->isCancelled()) {
            return;
        }

        if (! $this->currentSubscription->reactivate()) {
            session()->flash('error', 'This subscription has already ended. Please renew to continue.');

            return;
        }

        $this->currentSubscription->refresh();
        session()->flash('success', 'Your subscription has been reactivated!');
    }

    /** Turn automatic renewal with the saved card on or off (session 15). */
    public function toggleAutoRenew()
    {
        $this->requirePermission('manage subscription');

        $card = $this->savedCard();
        if (! $card) {
            return;
        }

        $card->forceFill(['auto_renew' => ! $card->auto_renew])->save();

        // A retry waiting for this card stops when renewal is switched off.
        if (! $card->auto_renew) {
            $this->cancelRetries();
        }

        session()->flash('success', $card->auto_renew
            ? "Automatic renewal is on. We'll charge {$card->label()} when your subscription is due."
            : 'Automatic renewal is off. Remember to pay before your subscription ends.');
    }

    /** Forget the saved card; this also turns automatic renewal off. */
    public function removeCard()
    {
        $this->requirePermission('manage subscription');

        $card = $this->savedCard();
        if (! $card) {
            return;
        }

        $label = $card->label();
        $card->delete();
        $this->cancelRetries();

        session()->flash('success', "{$label} has been removed. Automatic renewal is off.");
    }

    /** The current business's card only: the tenant scope plus an explicit check. */
    private function savedCard(): ?BillingCard
    {
        $tenantId = Auth::user()?->tenant_id;
        if (! $tenantId || ! app(AutoRenewal::class)->enabled()) {
            return null;
        }

        return BillingCard::where('tenant_id', $tenantId)->first();
    }

    private function cancelRetries(): void
    {
        SubscriptionRenewalAttempt::where('tenant_id', Auth::user()->tenant_id)
            ->whereNotNull('next_retry_at')
            ->update(['next_retry_at' => null]);
    }

    public function render()
    {
        $canManage = auth()->user()?->can('manage subscription') ?? false;
        $autoRenewal = app(AutoRenewal::class);
        $showBilling = $canManage && $autoRenewal->enabled() && Auth::user()?->tenant_id;

        return view('livewire.subscriptions.subscription-manager', [
            'canManage' => $canManage,
            'showAutoRenewal' => (bool) $showBilling,
            'savedCard' => $showBilling ? $this->savedCard() : null,
            'nextRenewal' => $showBilling ? $autoRenewal->nextRenewal((int) Auth::user()->tenant_id) : null,
            'renewalAttempts' => $showBilling
                ? SubscriptionRenewalAttempt::where('tenant_id', Auth::user()->tenant_id)->latest('id')->limit(12)->get()
                : collect(),
        ]);
    }
}
