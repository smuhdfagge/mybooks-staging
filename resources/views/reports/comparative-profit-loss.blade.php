{{-- Compare profit and loss (tables plan T6): two periods side by side. Costs in brackets. Amounts in ₦. --}}
@php
    $cur = $periodData['current'] ?? [];
    $prev = $periodData['previous'] ?? [];
    $np = $changes['netProfit'] ?? null;
    $rows = [
        ['label' => 'Income', 'key' => 'revenue', 'row' => 'sub'],
        ['label' => 'Cost of sales', 'key' => 'costOfGoodsSold', 'out' => true],
        ['label' => 'Gross profit', 'key' => 'grossProfit', 'row' => 'sub'],
        ['label' => 'Running costs', 'key' => 'operatingExpenses', 'out' => true],
        ['label' => 'Salaries and wages', 'key' => 'payroll', 'out' => true],
        ['label' => 'Total costs', 'key' => 'totalExpenses', 'out' => true, 'row' => 'sub'],
        ['label' => 'Profit or loss', 'key' => 'netProfit', 'row' => 'total'],
        ['label' => 'Gross margin', 'key' => 'grossMargin', 'pct' => true],
        ['label' => 'Profit margin', 'key' => 'profitMargin', 'pct' => true],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Compare profit and loss" :description="($cur['label'] ?? 'This period').' against '.($prev['label'] ?? 'the period before').'. Costs in brackets. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.profit-loss')">Profit and loss</x-table.menu-item>
                <x-table.menu-item :href="route('reports.comparative.balance-sheet', ['comparison_type' => $comparisonType])">Compare balance sheets</x-table.menu-item>
                <x-table.menu-item :href="route('reports.comparative.cash-flow', ['comparison_type' => $comparisonType])">Compare cash flow</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Compare profit and loss" :period="($cur['label'] ?? '').' and '.($prev['label'] ?? '')">
        @include('reports.partials.compare-filters', ['action' => route('reports.comparative.profit-loss')])

        <x-report.stats :cols="3">
            <x-report.stat :label="'Profit, '.($cur['label'] ?? 'this period')" :value="\App\Support\Figure::show($cur['netProfit'] ?? 0)" :tone="($cur['netProfit'] ?? 0) < 0 ? 'bad' : null"
                :hint="number_format($cur['profitMargin'] ?? 0, 1).'% of income'" />
            <x-report.stat :label="'Profit, '.($prev['label'] ?? 'last period')" :value="\App\Support\Figure::show($prev['netProfit'] ?? 0)" :tone="($prev['netProfit'] ?? 0) < 0 ? 'bad' : null"
                :hint="number_format($prev['profitMargin'] ?? 0, 1).'% of income'" />
            <x-report.stat label="Change" :value="$np ? ($np['difference'] >= 0 ? '+' : '-').number_format(abs($np['difference']), 2) : '—'"
                :tone="$np ? ($np['improved'] ? 'good' : 'bad') : null" :hint="$np ? ($np['improved'] ? 'Better than before' : 'Worse than before') : null" />
        </x-report.stats>

        @include('reports.partials.compare-table', ['rows' => $rows, 'caption' => 'Profit and loss compared'])
    </x-report.sheet>
</x-app-layout>
