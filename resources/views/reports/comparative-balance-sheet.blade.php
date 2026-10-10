{{-- Compare balance sheets (tables plan T6): two dates side by side. Amounts in ₦. --}}
@php
    $cur = $periodData['current'] ?? [];
    $prev = $periodData['previous'] ?? [];
    $ta = $changes['totalAssets'] ?? null;
    $rows = [
        ['label' => 'Assets', 'row' => 'section', 'key' => null],
        ['label' => 'Owed by customers', 'key' => 'accountsReceivable'],
        ['label' => 'Total assets', 'key' => 'totalAssets', 'row' => 'sub'],
        ['label' => 'Liabilities and equity', 'row' => 'section', 'key' => null],
        ['label' => 'Owed to suppliers', 'key' => 'accountsPayable'],
        ['label' => 'Total liabilities', 'key' => 'totalLiabilities', 'row' => 'sub'],
        ['label' => 'Equity', 'key' => 'equity', 'row' => 'sub'],
        ['label' => 'Total liabilities and equity', 'key' => 'totalLiabilitiesEquity', 'row' => 'total'],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Compare balance sheets" :description="'At the end of '.($cur['label'] ?? 'this period').' and of '.($prev['label'] ?? 'the period before').'. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.balance-sheet')">Balance sheet</x-table.menu-item>
                <x-table.menu-item :href="route('reports.comparative.profit-loss', ['comparison_type' => $comparisonType])">Compare profit and loss</x-table.menu-item>
                <x-table.menu-item :href="route('reports.comparative.cash-flow', ['comparison_type' => $comparisonType])">Compare cash flow</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Compare balance sheets" :period="($cur['label'] ?? '').' and '.($prev['label'] ?? '')">
        @include('reports.partials.compare-filters', ['action' => route('reports.comparative.balance-sheet')])

        <x-report.stats :cols="3">
            <x-report.stat :label="'Total assets, '.($cur['label'] ?? 'this period')" :value="\App\Support\Figure::show($cur['totalAssets'] ?? 0)"
                :hint="isset($cur['asOf']) ? 'At '.\Carbon\Carbon::parse($cur['asOf'])->format('j M Y') : null" />
            <x-report.stat :label="'Total assets, '.($prev['label'] ?? 'last period')" :value="\App\Support\Figure::show($prev['totalAssets'] ?? 0)"
                :hint="isset($prev['asOf']) ? 'At '.\Carbon\Carbon::parse($prev['asOf'])->format('j M Y') : null" />
            <x-report.stat label="Change in equity" :value="isset($changes['equity']) ? ($changes['equity']['difference'] >= 0 ? '+' : '-').number_format(abs($changes['equity']['difference']), 2) : '—'"
                :tone="isset($changes['equity']) ? ($changes['equity']['improved'] ? 'good' : 'bad') : null" />
        </x-report.stats>

        @include('reports.partials.compare-table', ['rows' => $rows, 'caption' => 'Balance sheets compared'])
    </x-report.sheet>
</x-app-layout>
