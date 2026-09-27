<?php

namespace App\Livewire\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;
use App\Livewire\Concerns\ChecksPermissions;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SubscriptionManager extends Component
{
    use ChecksPermissions;

    public $currentSubscription;
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
        
        if (!$tenant) {
            $this->plans = Plan::active()->ordered()->get();
            return;
        }
        
        $this->currentSubscription = $tenant->activeSubscription;
        $this->currentPlan = $tenant->currentPlan();
        $this->plans = Plan::active()->ordered()->get();
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

    public function changePlan()
    {
        $this->requirePermission('manage subscription');

        $this->validate([
            'selectedPlanId' => 'required|exists:plans,id',
            'selectedBillingCycle' => 'required|in:monthly,annual',
        ]);

        $tenant = Auth::user()->tenant;
        $newPlan = Plan::findOrFail($this->selectedPlanId);

        // Validate billing cycle is allowed
        if (!$newPlan->allowsBillingCycle($this->selectedBillingCycle)) {
            session()->flash('error', "The {$newPlan->name} plan does not support {$this->selectedBillingCycle} billing.");
            return;
        }

        // Get the price for the selected cycle
        $amount = $newPlan->getPriceForCycle($this->selectedBillingCycle);
        $startsAt = now();
        $endsAt = $this->selectedBillingCycle === Subscription::CYCLE_MONTHLY 
            ? $startsAt->copy()->addMonth() 
            : $startsAt->copy()->addYear();

        // Cancel current subscription if exists
        if ($this->currentSubscription) {
            $this->currentSubscription->cancel('Upgraded to ' . $newPlan->name);
        }

        // Create new subscription
        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $newPlan->id,
            'billing_cycle' => $this->selectedBillingCycle,
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => $amount,
            'currency' => $tenant->currency ?? 'NGN',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->currentSubscription = $subscription;
        $this->currentPlan = $newPlan;
        $this->closeUpgradeModal();

        session()->flash('success', "Successfully changed to {$newPlan->name} plan!");
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
            session()->flash('success', 'Your subscription has been cancelled. You will have access until ' . $this->currentSubscription->ends_at->format('M d, Y') . '.');
        }

        $this->closeCancelModal();
    }

    public function reactivateSubscription()
    {
        $this->requirePermission('manage subscription');

        if ($this->currentSubscription && $this->currentSubscription->isCancelled()) {
            $this->currentSubscription->update([
                'status' => Subscription::STATUS_ACTIVE,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ]);
            $this->currentSubscription->refresh();
            session()->flash('success', 'Your subscription has been reactivated!');
        }
    }

    public function render()
    {
        return view('livewire.subscriptions.subscription-manager', [
            'canManage' => auth()->user()?->can('manage subscription') ?? false,
        ]);
    }
}
