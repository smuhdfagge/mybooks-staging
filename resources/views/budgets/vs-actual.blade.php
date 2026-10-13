{{-- Budget and actual (tables plan T7): each account's budget so far against what happened, and whether it is better or worse. Amounts in ₦. --}}
@php
    $through = $ytdUtilization['through'] ?? null;
    $monthName = $through ? \Illuminate\Support\Str::ucfirst($months[$through] ?? $through) : null;
    $period = $through ? 'January to '.$monthName.' '.$budget->fiscal_year : 'Not started yet';
    $groups = ['income' => 'Income', 'expense' => 'Spending'];
    $sign = fn ($v) => (round((float) $v, 2) > 0 ? '+' : '').\App\Support\Figure::show($v, false);
    $tone = fn ($v) => round((float) $v, 2) < 0 ? 'tbl-late' : (round((float) $v, 2) > 0 ? 'text-green-800 dark:text-green-300' : 'tbl-zero');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="'Budget and actual: '.$budget->name" :description="'What was planned and what happened, '.($through ? 'January to '.$monthName : 'by account').'. A plus is better than budget (more income, less spending). Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('budgets.show', $budget)">The budget</x-table.menu-item>
                <x-table.menu-item :href="route('budgets.index')">All budgets</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                <x-table.dropdown label="Export" align="right">
                    <x-table.menu-item print>Print</x-table.menu-item>
                </x-table.dropdown>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <x-report.sheet :title="'Budget and actual: '.$budget->name" :period="$period">
        <x-report.stats :cols="4">
            <x-report.stat label="Spending budget so far" :value="\App\Support\Figure::show($ytdUtilization['total_budget_ytd'])" :hint="$period" />
            <x-report.stat label="Spent so far" :value="\App\Support\Figure::show($ytdUtilization['total_actual_ytd'])" />
            <x-report.stat label="Spending budget used" :value="\App\Support\Figure::percent($ytdUtilization['total_actual_ytd'], $ytdUtilization['total_budget_ytd'])" :tone="$ytdUtilization['utilization_percent'] > 100 ? 'bad' : null" />
            <x-report.stat label="Spending budget left" :value="\App\Support\Figure::show($ytdUtilization['remaining'])" :tone="$ytdUtilization['remaining'] < 0 ? 'bad' : null" />
        </x-report.stats>

        @if ($ytdUtilization['over_budget_count'] > 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100">
                <p class="font-medium">{{ $ytdUtilization['over_budget_count'] }} {{ \Illuminate\Support\Str::plural('account', $ytdUtilization['over_budget_count']) }} worse than budget</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($ytdUtilization['over_budget_accounts'] as $item)
                        <li>{{ $item['account_name'] }}: {{ number_format(abs($item['variance']), 2) }} {{ $item['account_type'] === 'income' ? 'below' : 'over' }} budget ({{ number_format(abs($item['variance_percent']), 1) }}%)</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($comparison->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="Nothing to compare yet" :text="$through ? 'This budget has no lines.' : 'This budget is for a year that has not started.'" />
            </div>
        @else
            <x-table caption="Budget and actual, by account">
                <x-slot name="head">
                    <x-table.th>Account</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Budget</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Actual</x-table.th>
                    <x-table.th num>Better or worse</x-table.th>
                    <x-table.th num class="hidden md:table-cell">%</x-table.th>
                    <x-table.th class="hidden md:table-cell">Status</x-table.th>
                </x-slot>
                @foreach ($groups as $type => $heading)
                    @php $rows = $comparison->where('account_type', $type); @endphp
                    @continue($rows->isEmpty())
                    <tr class="rpt-section"><td colspan="6">{{ $heading }}</td></tr>
                    @foreach ($rows as $item)
                        <tr>
                            <td class="rpt-in1"><span class="tbl-muted">{{ $item['account_code'] }}</span> {{ $item['account_name'] }}</td>
                            <td class="num hidden sm:table-cell">@fig($item['budgeted'], false)</td>
                            <td class="num hidden sm:table-cell">@fig($item['actual'], false)</td>
                            <td class="num {{ $tone($item['variance']) }}">{{ $sign($item['variance']) }}</td>
                            <td class="num hidden md:table-cell {{ $tone($item['variance']) }}">{{ (float) $item['budgeted'] != 0 ? (($item['variance'] > 0 ? '+' : '').number_format($item['variance_percent'], 1).'%') : '—' }}</td>
                            <td class="hidden md:table-cell">
                                @if ($item['is_over_budget'])
                                    <x-status-badge status="overdue" :label="$type === 'income' ? 'Below budget' : 'Over budget'" />
                                @else
                                    <x-status-badge status="completed" :label="$type === 'income' ? 'On or above' : 'Within budget'" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr class="rpt-sub">
                        <td class="rpt-in1">Total {{ strtolower($heading) }}</td>
                        <td class="num hidden sm:table-cell">@fig($rows->sum('budgeted'), false)</td>
                        <td class="num hidden sm:table-cell">@fig($rows->sum('actual'), false)</td>
                        <td class="num {{ $tone($rows->sum('variance')) }}">{{ $sign($rows->sum('variance')) }}</td>
                        <td class="hidden md:table-cell"></td>
                        <td class="hidden md:table-cell"></td>
                    </tr>
                @endforeach
                @php
                    $inc = $comparison->where('account_type', 'income'); $exp = $comparison->where('account_type', 'expense');
                    $profitBudget = $inc->sum('budgeted') - $exp->sum('budgeted'); $profitActual = $inc->sum('actual') - $exp->sum('actual');
                @endphp
                <tr class="rpt-total">
                    <td>Profit</td>
                    <td class="num hidden sm:table-cell">@fig($profitBudget)</td>
                    <td class="num hidden sm:table-cell">@fig($profitActual)</td>
                    <td class="num {{ $tone($profitActual - $profitBudget) }}">{{ $sign($profitActual - $profitBudget) }}</td>
                    <td class="hidden md:table-cell"></td>
                    <td class="hidden md:table-cell"></td>
                </tr>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
