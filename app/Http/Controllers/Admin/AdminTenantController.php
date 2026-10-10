<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtendSubscriptionRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Models\ActivityLog;
use App\Models\BillingCard;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionRenewalAttempt;
use App\Models\Tenant;
use App\Services\AdminAuditService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
    /** Status tab => label on the tenants list. */
    public const LIST_TABS = ['active' => 'Paying', 'trial' => 'On trial', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'no_subscription' => 'No plan'];

    public function list(Request $request)
    {
        // Search and plan narrow every tab; the tab picks the status.
        $base = function () use ($request) {
            $query = Tenant::query();
            if ($request->filled('plan')) {
                $query->whereHas('activeSubscription', fn ($q) => $q->where('plan_id', $request->plan));
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            }

            return $query;
        };

        $tabs = ['' => ['label' => 'All', 'count' => $base()->count(), 'href' => $this->listUrl($request, '')]];
        foreach (self::LIST_TABS as $key => $label) {
            $tabs[$key] = ['label' => $label, 'count' => $this->byStatus($base(), $key)->count(), 'href' => $this->listUrl($request, $key)];
        }

        $status = array_key_exists((string) $request->status, self::LIST_TABS) ? (string) $request->status : '';
        $tenants = $this->byStatus($base(), $status)
            ->with('activeSubscription.plan')->withCount('users')
            ->latest()->paginate(25)->withQueryString();
        $plans = Plan::active()->ordered()->get();

        return view('admin.tenants.list', compact('tenants', 'plans', 'tabs', 'status'));
    }

    /** @param Builder<Tenant> $query */
    private function byStatus($query, string $status)
    {
        return match ($status) {
            'active' => $query->whereHas('subscriptions', fn ($q) => $q->where('status', 'active')->where(fn ($q2) => $q2->whereNull('ends_at')->orWhere('ends_at', '>', now()))),
            'trial' => $query->whereHas('subscriptions', fn ($q) => $q->where('status', 'trialing')->where('ends_at', '>', now())),
            'cancelled' => $query->whereHas('subscriptions', fn ($q) => $q->where('status', 'cancelled')),
            'expired' => $query->whereHas('subscriptions', fn ($q) => $q->where('ends_at', '<', now())->whereNotIn('status', ['cancelled'])),
            'no_subscription' => $query->doesntHave('subscriptions'),
            default => $query,
        };
    }

    private function listUrl(Request $request, string $status): string
    {
        return route('admin.tenants.list', array_filter(['status' => $status, 'search' => $request->search, 'plan' => $request->plan], fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Display tenant details
     */
    public function show(Tenant $tenant)
    {
        $tenant->load(['users', 'subscriptions.plan', 'activeSubscription.plan']);
        $plans = Plan::active()->ordered()->get();

        // Auto-renewal status and the last automatic charge (session 15)
        $billingCard = BillingCard::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $lastRenewalAttempt = SubscriptionRenewalAttempt::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->latest('id')->first();

        return view('admin.tenants.show', compact('tenant', 'plans', 'billingCard', 'lastRenewalAttempt'));
    }

    /**
     * Update tenant subscription
     */
    public function updateSubscription(UpdateSubscriptionRequest $request, Tenant $tenant)
    {
        $validated = $request->validated();

        $subscription = $tenant->activeSubscription;

        if ($subscription) {
            $before = $subscription->getAttributes();
            $subscription->update($validated);
            AdminAuditService::logChange("changed the subscription of business '{$tenant->name}' (#{$tenant->id})", $subscription, $before);
        } else {
            $plan = Plan::findOrFail($validated['plan_id']);
            $endDate = $validated['billing_cycle'] === 'monthly'
                ? now()->addMonth()
                : now()->addYear();

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'status' => $validated['status'],
                'starts_at' => now(),
                'ends_at' => $endDate,
            ]);
            AdminAuditService::log(
                ActivityLog::ACTION_CREATED,
                "created a subscription for business '{$tenant->name}' (#{$tenant->id})",
                $subscription,
                null,
                $subscription->only(['tenant_id', 'plan_id', 'billing_cycle', 'status', 'starts_at', 'ends_at']),
            );
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

        $before = $subscription->getAttributes();
        $from = $subscription->ends_at && $subscription->ends_at->isFuture() ? $subscription->ends_at : now();
        $subscription->ends_at = Carbon::parse($from)->addDays($validated['extension_days']);
        $subscription->status = Subscription::STATUS_ACTIVE;
        $subscription->starts_at ??= now();
        $subscription->save();

        AdminAuditService::logChange(
            "extended the subscription of business '{$tenant->name}' (#{$tenant->id}) by {$validated['extension_days']} days",
            $subscription,
            $before
        );

        return back()->with('success', "Subscription extended by {$validated['extension_days']} days.");
    }

    /**
     * Toggle tenant status
     */
    public function toggleStatus(Tenant $tenant)
    {
        $before = $tenant->getAttributes();
        $tenant->is_active = ! $tenant->is_active;
        $tenant->save();

        $status = $tenant->is_active ? 'activated' : 'deactivated';
        AdminAuditService::logChange("{$status} business '{$tenant->name}' (#{$tenant->id})", $tenant, $before);

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

        $before = $subscription->getAttributes();
        $subscription->status = 'cancelled';
        $subscription->save();
        AdminAuditService::logChange("cancelled the subscription of business '{$tenant->name}' (#{$tenant->id})", $subscription, $before);

        return back()->with('success', 'Subscription cancelled successfully.');
    }
}
