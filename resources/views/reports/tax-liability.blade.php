{{-- Tax owed by period (tables plan T6): tax on invoices less tax on bills, month by month, from the documents. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $rate = fn ($r) => rtrim(rtrim(number_format((float) $r, 2), '0'), '.').'%';
    $groups = ['month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Tax owed by period" :description="'Tax on your invoices less tax on your bills, '.$from.' to '.$to.'. Credits in brackets. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.vat-gst-return', ['start_date' => $startDate, 'end_date' => $endDate])">VAT/GST return (from the ledger)</x-table.menu-item>
                <x-table.menu-item :href="route('reports.vat-return')">VAT return to file (Form 002)</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Tax owed by period" :period="$from.' to '.$to">
        <x-report.filters :action="route('reports.tax-liability')">
            <x-report.date name="start_date" label="From" :value="$startDate" />
            <x-report.date name="end_date" label="To" :value="$endDate" />
            <x-report.pick name="group_by" label="One line per" :options="$groups" :value="$groupBy" :all="null" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Tax on invoices" :value="\App\Support\Figure::show($totalTaxCollected)" :hint="'On '.number_format($totalTaxableSales, 2).' of sales'" />
            <x-report.stat label="Tax on bills" :value="\App\Support\Figure::show($totalTaxPaid)" :hint="'On '.number_format($totalTaxablePurchases, 2).' of purchases'" />
            <x-report.stat :label="$totalNetLiability >= 0 ? 'Tax owed' : 'Tax credit'" :value="number_format(abs($totalNetLiability), 2)" :tone="$totalNetLiability > 0 ? 'bad' : null" />
            <x-report.stat label="Average rate on sales" :value="\App\Support\Figure::percent($totalTaxCollected, $totalTaxableSales, 2)" />
        </x-report.stats>

        <x-table caption="Tax owed by period">
            <x-slot name="head">
                <x-table.th>{{ $groups[$groupBy] }}</x-table.th>
                <x-table.th num class="hidden lg:table-cell">Sales</x-table.th>
                <x-table.th num>Tax on invoices</x-table.th>
                <x-table.th num class="hidden lg:table-cell">Purchases</x-table.th>
                <x-table.th num>Tax on bills</x-table.th>
                <x-table.th num>Owed</x-table.th>
                <x-table.th num class="hidden sm:table-cell">Running total</x-table.th>
            </x-slot>
            @forelse ($periodsWithCumulative as $p)
                <tr>
                    <td>{{ $p['period_label'] }}</td>
                    <td class="num hidden lg:table-cell tbl-muted">@fig($p['taxable_sales'])</td>
                    <td class="num {{ \App\Support\Figure::tone($p['tax_collected']) }}">@fig($p['tax_collected'])</td>
                    <td class="num hidden lg:table-cell tbl-muted">@fig($p['taxable_purchases'])</td>
                    <td class="num {{ \App\Support\Figure::tone($p['tax_paid']) }}">@fig($p['tax_paid'])</td>
                    <td class="num font-medium">@fig($p['net_liability'])</td>
                    <td class="num hidden sm:table-cell tbl-muted">@fig($p['cumulative_liability'])</td>
                </tr>
            @empty
                <tr><td colspan="7" class="tbl-muted">No tax on invoices or bills in this period.</td></tr>
            @endforelse
            @if ($periodsWithCumulative->isNotEmpty())
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="num hidden lg:table-cell">@fig($totalTaxableSales)</td>
                        <td class="num">@fig($totalTaxCollected)</td>
                        <td class="num hidden lg:table-cell">@fig($totalTaxablePurchases)</td>
                        <td class="num">@fig($totalTaxPaid)</td>
                        <td class="num">@fig($totalNetLiability)</td>
                        <td class="num hidden sm:table-cell">@fig($totalNetLiability)</td>
                    </tr>
                </x-slot>
            @endif
        </x-table>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ([['Tax on invoices by rate', $taxByRateOutput, 'Sales'], ['Tax on bills by rate', $taxByRateInput, 'Purchases']] as [$caption, $rows, $what])
                <section class="space-y-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $caption }}</h3>
                    <x-table :caption="$caption">
                        <x-slot name="head">
                            <x-table.th>Rate</x-table.th>
                            <x-table.th num>{{ $what }}</x-table.th>
                            <x-table.th num>Tax</x-table.th>
                        </x-slot>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $rate($row->tax_rate) }}</td>
                                <td class="num">@fig($row->taxable_amount)</td>
                                <td class="num">@fig($row->tax_amount)</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="tbl-muted">None in this period.</td></tr>
                        @endforelse
                        @if ($rows->isNotEmpty())
                            <x-slot name="foot">
                                <tr><td>Total</td><td class="num">@fig($rows->sum('taxable_amount'))</td><td class="num">@fig($rows->sum('tax_amount'))</td></tr>
                            </x-slot>
                        @endif
                    </x-table>
                </section>
            @endforeach
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-400">Worked out from invoices and bills issued in the period; drafts and cancelled ones are left out. The VAT/GST return works from the ledger instead, so the two can differ for expenses and journals.</p>
    </x-report.sheet>
</x-app-layout>
