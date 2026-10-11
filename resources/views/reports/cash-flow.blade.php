{{-- Cash flow (tables plan T6): money in and out of cash and bank accounts, by activity. Outflows in brackets. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $out = fn ($v) => -abs((float) $v);
    $reconciles = abs(round($beginningCash + $netCashFlow - $endingCash, 2)) < 0.01;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Cash flow statement" :description="'Money in and out of your cash and bank accounts from '.$from.' to '.$to.'. Money out in brackets. Amounts in ₦.'"
            export="cash-flow" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.comparative.cash-flow')">Compare periods</x-table.menu-item>
                <x-table.menu-item :href="route('reports.profit-loss', ['start_date' => $startDate, 'end_date' => $endDate])">Profit and loss</x-table.menu-item>
                <x-table.menu-item :href="route('reports.balance-sheet', ['as_of' => $endDate])">Balance sheet</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Cash flow statement" :period="$from.' to '.$to">
        <x-report.filters :action="route('reports.cash-flow')">
            <x-report.date name="start_date" label="From" :value="$startDate" />
            <x-report.date name="end_date" label="To" :value="$endDate" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Cash at the start" :value="\App\Support\Figure::show($beginningCash)" :hint="$from" />
            <x-report.stat label="Money in" :value="\App\Support\Figure::show($totalInflows)" />
            <x-report.stat label="Money out" :value="\App\Support\Figure::show($totalOutflows)" />
            <x-report.stat label="Cash at the end" :value="\App\Support\Figure::show($endingCash)" :hint="$to" :tone="$endingCash < 0 ? 'bad' : null" />
        </x-report.stats>

        <x-table caption="Cash flow statement" class="max-w-3xl">
            <x-slot name="head">
                <x-table.th>Activity</x-table.th>
                <x-table.th num>{{ $from }} to {{ $to }}</x-table.th>
            </x-slot>

            <tr class="rpt-section"><td colspan="2">Day-to-day trading</td></tr>
            <tr><td class="rpt-in2">Received from customers</td><td class="num {{ \App\Support\Figure::tone($paymentsReceived) }}">@fig($paymentsReceived)</td></tr>
            <tr><td class="rpt-in2">Paid to suppliers</td><td class="num {{ \App\Support\Figure::tone($paymentsMade) }}">@fig($out($paymentsMade))</td></tr>
            <tr><td class="rpt-in2">Expenses paid</td><td class="num {{ \App\Support\Figure::tone($expensesPaid) }}">@fig($out($expensesPaid))</td></tr>
            <tr><td class="rpt-in2">Salaries and wages paid</td><td class="num {{ \App\Support\Figure::tone($payrollPaid) }}">@fig($out($payrollPaid))</td></tr>
            <tr class="rpt-sub"><td>Net cash from trading</td><td class="num">@fig($netOperatingCashFlow)</td></tr>

            <tr class="rpt-section"><td colspan="2">Buying and selling fixed assets</td></tr>
            <tr><td class="rpt-in2">Fixed assets bought</td><td class="num {{ \App\Support\Figure::tone($fixedAssetPurchases) }}">@fig($out($fixedAssetPurchases))</td></tr>
            <tr><td class="rpt-in2">Fixed assets sold</td><td class="num {{ \App\Support\Figure::tone($fixedAssetSales) }}">@fig($fixedAssetSales)</td></tr>
            <tr class="rpt-sub"><td>Net cash from fixed assets</td><td class="num">@fig($netInvestingCashFlow)</td></tr>

            <tr class="rpt-section"><td colspan="2">Loans and owners</td></tr>
            <tr><td class="rpt-in2">Loans received</td><td class="num {{ \App\Support\Figure::tone($borrowingsReceived) }}">@fig($borrowingsReceived)</td></tr>
            <tr><td class="rpt-in2">Loans repaid</td><td class="num {{ \App\Support\Figure::tone($loanRepayments) }}">@fig($out($loanRepayments))</td></tr>
            <tr><td class="rpt-in2">Money put in by owners</td><td class="num {{ \App\Support\Figure::tone($capitalContributions) }}">@fig($capitalContributions)</td></tr>
            <tr><td class="rpt-in2">Drawings by owners</td><td class="num {{ \App\Support\Figure::tone($drawings) }}">@fig($out($drawings))</td></tr>
            <tr class="rpt-sub"><td>Net cash from loans and owners</td><td class="num">@fig($netFinancingCashFlow)</td></tr>

            <tr class="rpt-sub"><td>Net change in cash</td><td class="num">@fig($netCashFlow)</td></tr>
            <tr><td class="rpt-in1">Cash at the start, {{ $from }}</td><td class="num">@fig($beginningCash)</td></tr>
            <tr class="rpt-total"><td>Cash at the end, {{ $to }}</td><td class="num">@fig($endingCash)</td></tr>
        </x-table>

        @unless ($reconciles)
            <x-report.check :ok="false">Cash at the start plus the net change does not equal cash at the end. Check the cash and bank accounts.</x-report.check>
        @endunless
    </x-report.sheet>
</x-app-layout>
