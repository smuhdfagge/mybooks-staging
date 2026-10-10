{{-- Compare cash flow (tables plan T6): two periods side by side. Money out in brackets. Amounts in ₦. --}}
@php
    $cur = $periodData['current'] ?? [];
    $prev = $periodData['previous'] ?? [];
    $net = $changes['netCashFlow'] ?? null;
    $rows = [
        ['label' => 'Received from customers', 'key' => 'paymentsReceived'],
        ['label' => 'Paid to suppliers', 'key' => 'paymentsMade', 'out' => true],
        ['label' => 'Expenses paid', 'key' => 'expensesPaid', 'out' => true],
        ['label' => 'Salaries and wages paid', 'key' => 'payrollPaid', 'out' => true],
        ['label' => 'All money in', 'key' => 'totalInflows', 'row' => 'sub'],
        ['label' => 'All money out', 'key' => 'totalOutflows', 'out' => true, 'row' => 'sub'],
        ['label' => 'Net change in cash', 'key' => 'netCashFlow', 'row' => 'total'],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Compare cash flow" :description="($cur['label'] ?? 'This period').' against '.($prev['label'] ?? 'the period before').'. Money out in brackets. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.cash-flow')">Cash flow statement</x-table.menu-item>
                <x-table.menu-item :href="route('reports.comparative.profit-loss', ['comparison_type' => $comparisonType])">Compare profit and loss</x-table.menu-item>
                <x-table.menu-item :href="route('reports.comparative.balance-sheet', ['comparison_type' => $comparisonType])">Compare balance sheets</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Compare cash flow" :period="($cur['label'] ?? '').' and '.($prev['label'] ?? '')">
        @include('reports.partials.compare-filters', ['action' => route('reports.comparative.cash-flow')])

        <x-report.stats :cols="3">
            <x-report.stat :label="'Net cash, '.($cur['label'] ?? 'this period')" :value="\App\Support\Figure::show($cur['netCashFlow'] ?? 0)" :tone="($cur['netCashFlow'] ?? 0) < 0 ? 'bad' : null" />
            <x-report.stat :label="'Net cash, '.($prev['label'] ?? 'last period')" :value="\App\Support\Figure::show($prev['netCashFlow'] ?? 0)" :tone="($prev['netCashFlow'] ?? 0) < 0 ? 'bad' : null" />
            <x-report.stat label="Change" :value="$net ? ($net['difference'] >= 0 ? '+' : '-').number_format(abs($net['difference']), 2) : '—'"
                :tone="$net ? ($net['improved'] ? 'good' : 'bad') : null" />
        </x-report.stats>

        @include('reports.partials.compare-table', ['rows' => $rows, 'caption' => 'Cash flow compared'])
    </x-report.sheet>
</x-app-layout>
