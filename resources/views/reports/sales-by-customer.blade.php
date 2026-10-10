{{-- Sales by customer (tables plan T6): what each customer bought in a period. Drafts and cancelled invoices are left out. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $owed = round($totalSales - $totalPaid, 2);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Sales by customer" :description="'What each customer bought from '.$from.' to '.$to.', biggest first. Amounts in ₦.'"
            export="sales-by-customer" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.sales-by-item', ['start_date' => $startDate, 'end_date' => $endDate])">Sales by item</x-table.menu-item>
                <x-table.menu-item :href="route('reports.accounts-receivable')">Aged receivables</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Sales by customer" :period="$from.' to '.$to">
        <x-report.filters :action="route('reports.sales-by-customer')">
            <x-report.date name="start_date" label="From" :value="$startDate" />
            <x-report.date name="end_date" label="To" :value="$endDate" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Sales" :value="\App\Support\Figure::show($totalSales)" :hint="$customers->sum('invoices_count').' invoices'" />
            <x-report.stat label="Paid" :value="\App\Support\Figure::show($totalPaid)" />
            <x-report.stat label="Still owed" :value="\App\Support\Figure::show($owed)" />
            <x-report.stat label="Customers" :value="number_format($customers->count())" />
        </x-report.stats>

        @if ($customers->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No sales in this period" text="No invoices were issued between these dates." /></div>
        @else
            <x-table caption="Sales by customer">
                <x-slot name="head">
                    <x-table.th>Customer</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Invoices</x-table.th>
                    <x-table.th num>Sales</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Paid</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Still owed</x-table.th>
                    <x-table.th num>Share</x-table.th>
                </x-slot>
                @foreach ($customers as $customer)
                    @php $left = (float) ($customer->invoices_sum_total ?? 0) - (float) ($customer->invoices_sum_amount_paid ?? 0); @endphp
                    <tr>
                        <td class="rpt-wrap"><a href="{{ route('customers.show', $customer) }}" class="tbl-link">{{ $customer->name }}</a></td>
                        <td class="num hidden sm:table-cell">{{ number_format($customer->invoices_count) }}</td>
                        <td class="num font-medium">@fig($customer->invoices_sum_total)</td>
                        <td class="num hidden md:table-cell {{ \App\Support\Figure::tone($customer->invoices_sum_amount_paid) }}">@fig($customer->invoices_sum_amount_paid)</td>
                        <td class="num hidden sm:table-cell {{ \App\Support\Figure::tone($left) }}">@fig($left)</td>
                        <td class="num tbl-muted">{{ \App\Support\Figure::percent($customer->invoices_sum_total, $totalSales) }}</td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="num hidden sm:table-cell">{{ number_format($customers->sum('invoices_count')) }}</td>
                        <td class="num">@fig($totalSales)</td>
                        <td class="num hidden md:table-cell">@fig($totalPaid)</td>
                        <td class="num hidden sm:table-cell">@fig($owed)</td>
                        <td class="num">100%</td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
