{{-- Profit and loss (tables plan T6): income less costs for a period, account by account. Negatives in brackets. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $opex = round($operatingExpenses + $payroll, 2);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Profit and loss" :description="'Income less costs from '.$from.' to '.$to.'. Click an account to see its postings. Amounts in ₦.'"
            export="profit-loss" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.comparative.profit-loss')">Compare periods</x-table.menu-item>
                <x-table.menu-item :href="route('reports.balance-sheet', ['as_of' => $endDate])">Balance sheet</x-table.menu-item>
                <x-table.menu-item :href="route('reports.cash-flow', ['start_date' => $startDate, 'end_date' => $endDate])">Cash flow</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Profit and loss" :period="$from.' to '.$to">
        <x-report.filters :action="route('reports.profit-loss')">
            <x-report.date name="start_date" label="From" :value="$startDate" />
            <x-report.date name="end_date" label="To" :value="$endDate" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Income" :value="\App\Support\Figure::show($revenue)" />
            <x-report.stat label="Gross profit" :value="\App\Support\Figure::show($grossProfit)" :hint="\App\Support\Figure::percent($grossProfit, $revenue).' of income'" />
            <x-report.stat label="Running costs" :value="\App\Support\Figure::show($opex)" />
            <x-report.stat :label="$netProfit >= 0 ? 'Profit' : 'Loss'" :value="\App\Support\Figure::show($netProfit)" :tone="$netProfit < 0 ? 'bad' : 'good'"
                :hint="\App\Support\Figure::percent($netProfit, $revenue).' of income'" />
        </x-report.stats>

        <x-table caption="Profit and loss" class="max-w-3xl">
            <x-slot name="head">
                <x-table.th>Account</x-table.th>
                <x-table.th num>{{ $from }} to {{ $to }}</x-table.th>
            </x-slot>

            <tr class="rpt-section"><td colspan="2">Income</td></tr>
            @include('reports.partials.account-lines', ['lines' => $lines['income'], 'from' => $startDate, 'to' => $endDate])
            <tr class="rpt-sub"><td>Total income</td><td class="num">@fig($revenue)</td></tr>

            @if ($lines['cogs']->isNotEmpty())
                <tr class="rpt-section"><td colspan="2">Cost of sales</td></tr>
                @include('reports.partials.account-lines', ['lines' => $lines['cogs'], 'from' => $startDate, 'to' => $endDate])
                <tr class="rpt-sub"><td>Total cost of sales</td><td class="num">@fig($costOfGoodsSold)</td></tr>
            @endif
            <tr class="rpt-sub">
                <td>Gross profit <span class="font-normal tbl-muted">· {{ \App\Support\Figure::percent($grossProfit, $revenue) }} of income</span></td>
                <td class="num">@fig($grossProfit)</td>
            </tr>

            <tr class="rpt-section"><td colspan="2">Running costs</td></tr>
            @include('reports.partials.account-lines', ['lines' => $lines['operating']->concat($lines['payroll']), 'from' => $startDate, 'to' => $endDate])
            <tr class="rpt-sub"><td>Total running costs</td><td class="num">@fig($opex)</td></tr>

            <tr class="rpt-total">
                <td>{{ $netProfit >= 0 ? 'Profit' : 'Loss' }} for the period <span class="font-normal tbl-muted">· {{ \App\Support\Figure::percent($netProfit, $revenue) }} of income</span></td>
                <td class="num {{ $netProfit < 0 ? 'text-red-700 dark:text-red-300' : '' }}">@fig($netProfit)</td>
            </tr>
        </x-table>
    </x-report.sheet>
</x-app-layout>
