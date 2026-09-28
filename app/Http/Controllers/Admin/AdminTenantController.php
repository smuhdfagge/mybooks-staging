<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtendSubscriptionRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminTenantController extends Controller
{
    /**
     * Display tenant dashboard
     */
    public function index()
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'active_subscriptions' => Subscription::where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })->count(),
            'trial_subscriptions' => Subscription::where('status', 'trialing')
                ->where('ends_at', '>', now())->count(),
            'expired_subscriptions' => Subscription::where('ends_at', '<', now())
                ->whereNotIn('status', ['cancelled'])->count(),
            'monthly_revenue' => Subscription::where('status', 'active')
                ->where('billing_cycle', 'monthly')
                ->with('plan')
                ->get()
                ->sum(fn ($s) => $s->plan?->monthly_price ?? 0),
        ];

        $recentTenants = Tenant::with(['activeSubscription.plan'])
            ->latest()
            ->take(5)
            ->get();

        $expiringSubscriptions = Subscription::with(['tenant', 'plan'])
            ->whereIn('status', ['active', 'trialing'])
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->orderBy('ends_at')
            ->take(5)
            ->get();

        $plans = Plan::withCount(['subscriptions' => function ($query) {
            $query->whereIn('status', ['active', 'trialing']);
        }])->active()->ordered()->get();

        return view('admin.tenants.index', compact('stats', 'recentTenants', 'expiringSubscriptions', 'plans'));
    }

    /**
     * Display listing of tenants
     */
    public function list(Request $request)
    {
        $query = Tenant::with(['activeSubscription.plan', 'subscriptions']);

        // Filter by status
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->whereHas('subscriptions', function ($q) {
                        $q->where('status', 'active')
                            ->where(function ($q2) {
                                $q2->whereNull('ends_at')->orWhere('ends_at', '>', now());
                            });
                    });
                    break;
                case 'trial':
                    $query->whereHas('subscriptions', function ($q) {
                        $q->where('status', 'trialing')->where('ends_at', '>', now());
                    });
                    break;
                case 'cancelled':
                    $query->whereHas('subscriptions', function ($q) {
                        $q->where('status', 'cancelled');
                    });
                    break;
                case 'expired':
                    $query->whereHas('subscriptions', function ($q) {
                        $q->where('ends_at', '<', now())->whereNotIn('status', ['cancelled']);
                    });
                    break;
                case 'no_subscription':
                    $query->doesntHave('subscriptions');
                    break;
            }
        }

        // Filter by plan
        if ($request->filled('plan')) {
            $query->whereHas('activeSubscription', function ($q) use ($request) {
                $q->where('plan_id', $request->plan);
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $tenants = $query->latest()->paginate(15)->withQueryString();
        $plans = Plan::active()->ordered()->get();

        return view('admin.tenants.list', compact('tenants', 'plans'));
    }

    /**
     * Display tenant details
     */
    public function show(Tenant $tenant)
    {
        $tenant->load(['users', 'subscriptions.plan', 'activeSubscription.plan']);
        $plans = Plan::active()->ordered()->get();

        return view('admin.tenants.show', compact('tenant', 'plans'));
    }

    /**
     * Update tenant subscription
     */
    public function updateSubscription(UpdateSubscriptionRequest $request, Tenant $tenant)
    {
        $validated = $request->validated();

        $subscription = $tenant->activeSubscription;

        if ($subscription) {
            $subscription->update($validated);
        } else {
            $plan = Plan::findOrFail($validated['plan_id']);
            $endDate = $validated['billing_cycle'] === 'monthly'
                ? now()->addMonth()
                : now()->addYear();

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'status' => $validated['status'],
                'starts_at' => now(),
                'ends_at' => $endDate,
            ]);
        }

        return back()->with('success', 'Subscription updated successfully.');
    }

    /**
     * Extend tenant subscription
     */
    public function extendSubscription(ExtendSubscriptionRequest $request, Tenant $tenant)
    {
        $validated = $request->validated();

        // A subscription that has run out can be extended too: the extra
        // days then count from today and it becomes active again.
        $subscription = $tenant->activeSubscription ?? $tenant->latestSubscription;

        if (! $subscription) {
            return back()->with('error', 'This tenant has no subscription to extend.');
        }

        $from = $subscription->ends_at && $subscription->ends_at->isFuture() ? $subscription->ends_at : now();
        $subscription->ends_at = Carbon::parse($from)->addDays($validated['extension_days']);
        $subscription->status = Subscription::STATUS_ACTIVE;
        $subscription->starts_at ??= now();
        $subscription->save();

        return back()->with('success', "Subscription extended by {$validated['extension_days']} days.");
    }

    /**
     * Toggle tenant status
     */
    public function toggleStatus(Tenant $tenant)
    {
        $tenant->is_active = ! $tenant->is_active;
        $tenant->save();

        $status = $tenant->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Tenant {$status} successfully.");
    }

    /**
     * Cancel tenant subscription
     */
    public function cancelSubscription(Tenant $tenant)
    {
        $subscription = $tenant->activeSubscription;

        if (! $subscription) {
            return back()->with('error', 'No active subscription to cancel.');
        }

        $subscription->status = 'cancelled';
        $subscription->save();

        return back()->with('success', 'Subscription cancelled successfully.');
    }
}
