{{-- Balance sheet (tables plan T6): what the business owns and owes at a date. Negatives in brackets. Amounts in ₦. --}}
@php
    $asOfText = \Carbon\Carbon::parse($asOf)->format('j M Y');
    $fyText = \Carbon\Carbon::parse($fiscalYearStart)->format('j M Y');
    $from = $fiscalYearStart;
    $balanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;
    $lines = fn (array $groups, array $keys) => collect($keys)->flatMap(fn ($k) => $groups[$k] ?? collect());
    $currentAssets = $lines($assetDetails, ['cash', 'accounts_receivable', 'inventory', 'other_current']);
    $currentLiabilities = $lines($liabilityDetails, ['accounts_payable', 'credit_card', 'other_current']);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Balance sheet" :description="'What the business owns and owes at '.$asOfText.'. Click an account to see its postings. Amounts in ₦.'"
            export="balance-sheet" :filters="['as_of' => $asOf]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.comparative.balance-sheet')">Compare periods</x-table.menu-item>
                <x-table.menu-item :href="route('reports.profit-loss', ['start_date' => $fiscalYearStart, 'end_date' => $asOf])">Profit and loss</x-table.menu-item>
                <x-table.menu-item :href="route('reports.trial-balance', ['as_of' => $asOf])">Trial balance</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Balance sheet" :period="'As at '.$asOfText">
        <x-report.filters :action="route('reports.balance-sheet')">
            <x-report.date name="as_of" label="As at" :value="$asOf" />
        </x-report.filters>

        <x-report.stats :cols="3">
            <x-report.stat label="Total assets" :value="\App\Support\Figure::show($totalAssets)" />
            <x-report.stat label="Total liabilities" :value="\App\Support\Figure::show($totalLiabilities)" />
            <x-report.stat label="Total equity" :value="\App\Support\Figure::show($totalEquity)" :tone="$totalEquity < 0 ? 'bad' : null" />
        </x-report.stats>

        <x-report.check :ok="$balanced">
            @if ($balanced)
                Assets equal liabilities plus equity.
            @else
                Assets and liabilities plus equity differ by {{ number_format(abs($totalAssets - $totalLiabilitiesAndEquity), 2) }}. Check the trial balance.
            @endif
        </x-report.check>

        <x-table caption="Balance sheet" class="max-w-3xl">
            <x-slot name="head">
                <x-table.th>Account</x-table.th>
                <x-table.th num>{{ $asOfText }}</x-table.th>
            </x-slot>

            <tr class="rpt-section"><td colspan="2">Assets</td></tr>
            <tr><td class="rpt-in1 font-medium" colspan="2">Current assets</td></tr>
            @include('reports.partials.account-lines', ['lines' => $currentAssets, 'from' => $from, 'to' => $asOf])
            <tr class="rpt-sub"><td class="rpt-in1">Total current assets</td><td class="num">@fig($totalCurrentAssets)</td></tr>
            @if (abs($fixedAssets) >= 0.005)
                <tr><td class="rpt-in1 font-medium" colspan="2">Fixed assets</td></tr>
                @include('reports.partials.account-lines', ['lines' => $assetDetails['fixed'], 'from' => $from, 'to' => $asOf])
                <tr class="rpt-sub"><td class="rpt-in1">Total fixed assets</td><td class="num">@fig($fixedAssets)</td></tr>
            @endif
            <tr class="rpt-total"><td>Total assets</td><td class="num">@fig($totalAssets)</td></tr>

            <tr class="rpt-section"><td colspan="2">Liabilities</td></tr>
            <tr><td class="rpt-in1 font-medium" colspan="2">Current liabilities</td></tr>
            @include('reports.partials.account-lines', ['lines' => $currentLiabilities, 'from' => $from, 'to' => $asOf])
            <tr class="rpt-sub"><td class="rpt-in1">Total current liabilities</td><td class="num">@fig($totalCurrentLiabilities)</td></tr>
            @if (abs($longTermLiabilities) >= 0.005)
                <tr><td class="rpt-in1 font-medium" colspan="2">Long-term liabilities</td></tr>
                @include('reports.partials.account-lines', ['lines' => $liabilityDetails['long_term'], 'from' => $from, 'to' => $asOf])
                <tr class="rpt-sub"><td class="rpt-in1">Total long-term liabilities</td><td class="num">@fig($longTermLiabilities)</td></tr>
            @endif
            <tr class="rpt-sub"><td>Total liabilities</td><td class="num">@fig($totalLiabilities)</td></tr>

            <tr class="rpt-section"><td colspan="2">Equity</td></tr>
            @include('reports.partials.account-lines', ['lines' => $lines($equityDetails, ['capital', 'retained_earnings']), 'from' => $from, 'to' => $asOf])
            @if (abs($priorYearsProfit ?? 0) >= 0.01)
                <tr>
                    <td class="rpt-wrap rpt-in2">Profit of earlier years not yet closed <span class="block text-xs tbl-muted">Run the year-end close to move it into retained earnings.</span></td>
                    <td class="num">@fig($priorYearsProfit)</td>
                </tr>
            @endif
            <tr>
                <td class="rpt-wrap rpt-in2">
                    <a href="{{ route('reports.profit-loss', ['start_date' => $fiscalYearStart, 'end_date' => $asOf]) }}" class="tbl-link font-normal">Profit this year</a>
                    <span class="tbl-muted">since {{ $fyText }}</span>
                </td>
                <td class="num">@fig($netIncome)</td>
            </tr>
            <tr class="rpt-sub"><td>Total equity</td><td class="num">@fig($totalEquity)</td></tr>
            <tr class="rpt-total"><td>Total liabilities and equity</td><td class="num">@fig($totalLiabilitiesAndEquity)</td></tr>
        </x-table>
    </x-report.sheet>
</x-app-layout>
