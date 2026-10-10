<?php

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardService;
use Livewire\Component;

/**
 * The lower dashboard cards (dashboard upgrade): cash flow, who owes
 * whom, top customers, where the money goes and recent activity. Loaded
 * just after the page opens (lazy), so the main cards are never held up.
 * Each card needs its own dashboard permission (config/dashboard.php).
 */
class LowerCards extends Component
{
    public function placeholder()
    {
        return view('livewire.dashboard.lower-cards-placeholder');
    }

    public function render(DashboardService $dashboard)
    {
        $user = auth()->user();
        abort_unless($user?->can('view dashboard'), 403);
        $tenantId = (int) $user->tenant_id;
        $show = fn (string $widget) => $user->can("{$widget} dashboard-widgets");

        return view('livewire.dashboard.lower-cards', [
            'cashFlow' => $show('cash-position') ? $dashboard->cashFlow($tenantId) : null,
            'receivables' => $show('outstanding-receivables') ? $dashboard->receivables($tenantId) : null,
            'payables' => $show('pending-bills') ? $dashboard->payables($tenantId) : null,
            'customers' => $show('top-customers') ? $dashboard->topCustomers($tenantId) : null,
            'spend' => $show('expense-breakdown') ? $dashboard->expenseBreakdown($tenantId) : null,
            'recent' => $show('recent-invoices') ? $dashboard->recentActivity($user) : null,
        ]);
    }
}
