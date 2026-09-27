<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantManagementController extends Controller
{
    /**
     * Display the tenant management dashboard.
     */
    public function index()
    {
        // Get statistics
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::whereHas('subscriptions', function ($query) {
                $query->where('status', Subscription::STATUS_ACTIVE);
            })->count(),
            'trial_tenants' => Tenant::whereHas('subscriptions', function ($query) {
                $query->where('status', Subscription::STATUS_TRIALING);
            })->count(),
            'cancelled_tenants' => Tenant::whereHas('subscriptions', function ($query) {
                $query->where('status', Subscription::STATUS_CANCELLED);
            })->count(),
            'expired_tenants' => Tenant::whereHas('subscriptions', function ($query) {
                $query->where('status', Subscription::STATUS_EXPIRED);
            })->count(),
        ];

        // Revenue stats
        $stats['monthly_revenue'] = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('billing_cycle', 'monthly')
            ->sum('amount');
        
        $stats['annual_revenue'] = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('billing_cycle', 'annual')
            ->sum('amount');

        // Plan distribution
        $planDistribution = Plan::withCount(['subscriptions' => function ($query) {
            $query->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING]);
        }])->get();

        // Recent tenants
        $recentTenants = Tenant::with(['activeSubscription.plan', 'users'])
            ->latest()
            ->take(5)
            ->get();

        // Expiring soon (within 7 days)
        $expiringSoon = Subscription::with(['tenant', 'plan'])
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->where('ends_at', '<=', now()->addDays(7))
            ->where('ends_at', '>', now())
            ->orderBy('ends_at')
            ->take(10)
            ->get();

        return view('tenants.index', compact('stats', 'planDistribution', 'recentTenants', 'expiringSoon'));
    }

    /**
     * Display list of all tenants.
     */
    public function list(Request $request)
    {
        $query = Tenant::with(['activeSubscription.plan', 'users']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by plan
        if ($request->filled('plan')) {
            $query->whereHas('subscriptions', function ($q) use ($request) {
                $q->where('plan_id', $request->plan)
                  ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING]);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereHas('subscriptions', function ($q) {
                    $q->where('status', Subscription::STATUS_ACTIVE);
                });
            } elseif ($request->status === 'trial') {
                $query->whereHas('subscriptions', function ($q) {
                    $q->where('status', Subscription::STATUS_TRIALING);
                });
            } elseif ($request->status === 'cancelled') {
                $query->whereHas('subscriptions', function ($q) {
                    $q->where('status', Subscription::STATUS_CANCELLED);
                });
            } elseif ($request->status === 'expired') {
                $query->whereHas('subscriptions', function ($q) {
                    $q->where('status', Subscription::STATUS_EXPIRED);
                });
            } elseif ($request->status === 'no_subscription') {
                $query->doesntHave('subscriptions');
            }
        }

        $tenants = $query->latest()->paginate(15)->withQueryString();
        $plans = Plan::active()->ordered()->get();

        return view('tenants.list', compact('tenants', 'plans'));
    }

    /**
     * Show tenant details.
     */
    public function show(Tenant $tenant)
    {
        $tenant->load(['subscriptions.plan', 'users']);
        $plans = Plan::active()->ordered()->get();

        return view('tenants.show', compact('tenant', 'plans'));
    }

    /**
     * Update tenant's subscription.
     */
    public function updateSubscription(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'status' => 'required|in:active,trialing,cancelled,expired,past_due',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Get or create subscription
        $subscription = $tenant->activeSubscription;

        if ($subscription) {
            $amount = $plan->getPriceForCycle($validated['billing_cycle']);
            
            $subscription->update([
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'status' => $validated['status'],
                'amount' => $amount,
            ]);
        } else {
            $amount = $plan->getPriceForCycle($validated['billing_cycle']);
            $startsAt = now();
            $endsAt = $validated['billing_cycle'] === 'monthly' 
                ? $startsAt->copy()->addMonth() 
                : $startsAt->copy()->addYear();

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'status' => $validated['status'],
                'amount' => $amount,
                'currency' => $tenant->currency ?? 'NGN',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }

        return redirect()->route('tenants.show', $tenant)
            ->with('success', 'Subscription updated successfully.');
    }

    /**
     * Extend tenant's subscription.
     */
    public function extendSubscription(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $subscription = $tenant->activeSubscription;

        if ($subscription) {
            $subscription->update([
                'ends_at' => $subscription->ends_at->addDays($validated['days']),
            ]);

            return redirect()->route('tenants.show', $tenant)
                ->with('success', "Subscription extended by {$validated['days']} days.");
        }

        return redirect()->route('tenants.show', $tenant)
            ->with('error', 'No active subscription found.');
    }

    /**
     * Toggle tenant active status.
     */
    public function toggleStatus(Tenant $tenant)
    {
        $tenant->update([
            'is_active' => !$tenant->is_active,
        ]);

        $status = $tenant->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Tenant {$status} successfully.");
    }

    /**
     * Cancel tenant's subscription.
     */
    public function cancelSubscription(Request $request, Tenant $tenant)
    {
        $subscription = $tenant->activeSubscription;

        if ($subscription) {
            $subscription->cancel($request->input('reason', 'Cancelled by administrator'));

            return redirect()->route('tenants.show', $tenant)
                ->with('success', 'Subscription cancelled successfully.');
        }

        return redirect()->route('tenants.show', $tenant)
            ->with('error', 'No active subscription found.');
    }
}
