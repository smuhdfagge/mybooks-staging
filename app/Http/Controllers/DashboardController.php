<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;

/**
 * The dashboard (dashboard upgrade): how the business is doing at a
 * glance. Every figure comes from DashboardService; the lower cards load
 * just after the page through the Dashboard\LowerCards component.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;

        // The chosen period is remembered for the rest of the visit.
        $choice = [
            'period' => $request->query('period', session('dashboard.period')),
            'compare' => $request->query('compare', session('dashboard.compare')),
        ];
        $period = $dashboard->period($tenantId, $choice['period'], $choice['compare']);
        session(['dashboard.period' => $period->key, 'dashboard.compare' => $period->compareKey]);

        $show = fn (string $widget) => $user->can("{$widget} dashboard-widgets");
        $needsSummary = $show('cash-position') || $show('total-revenue') || $show('monthly-expenses') || $show('profit');

        return view('dashboard', [
            'period' => $period,
            'summary' => $needsSummary ? $dashboard->summary($tenantId, $period) : null,
            'months' => $needsSummary || $show('revenue-chart') ? $dashboard->months($tenantId) : [],
            'attention' => $show('attention-list') ? $dashboard->attention($user) : null,
            'gettingStarted' => $dashboard->gettingStarted($user),
            'newMenu' => $show('quick-actions') ? $this->newMenu($user) : [],
            'greeting' => $this->greeting(),
            'updatedAt' => now(),
        ]);
    }

    public function hideGettingStarted(Request $request)
    {
        $tenant = Tenant::findOrFail($request->user()->tenant_id);
        $tenant->settings = array_merge($tenant->settings ?? [], ['dashboard_start_hidden' => true]);
        $tenant->save();

        return redirect()->route('dashboard');
    }

    /** @return list<array{label: string, url: string}> */
    private function newMenu($user): array
    {
        $items = [
            ['Invoice', 'invoices.create', 'create invoices'],
            ['Payment received', 'payments-received.create', 'create payments-received'],
            ['Bill', 'bills.create', 'create bills'],
            ['Expense', 'expenses.create', 'create expenses'],
            ['Customer', 'customers.create', 'create customers'],
            ['Supplier', 'vendors.create', 'create vendors'],
            ['Item', 'items.create', 'create items'],
            ['Journal', 'journals.create', 'create journals'],
        ];

        return array_values(array_map(
            fn ($i) => ['label' => $i[0], 'url' => route($i[1])],
            array_filter($items, fn ($i) => $user->can($i[2]))
        ));
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
