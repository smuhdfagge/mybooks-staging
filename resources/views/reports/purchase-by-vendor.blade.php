{{-- Purchases by supplier (tables plan T6): what you bought from each supplier in a period. Drafts and cancelled bills are left out. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $owed = round($totalPurchases - $totalPaid, 2);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Purchases by supplier" :description="'What you bought from each supplier from '.$from.' to '.$to.', biggest first. Amounts in ₦.'"
            export="purchase-by-vendor" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.accounts-payable')">Aged payables</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Purchases by supplier" :period="$from.' to '.$to">
        <x-report.filters :action="route('reports.purchase-by-vendor')">
            <x-report.date name="start_date" label="From" :value="$startDate" />
            <x-report.date name="end_date" label="To" :value="$endDate" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Purchases" :value="\App\Support\Figure::show($totalPurchases)" :hint="$vendors->sum('bills_count').' bills'" />
            <x-report.stat label="Paid" :value="\App\Support\Figure::show($totalPaid)" />
            <x-report.stat label="Still to pay" :value="\App\Support\Figure::show($owed)" />
            <x-report.stat label="Suppliers" :value="number_format($vendors->count())" />
        </x-report.stats>

        @if ($vendors->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No purchases in this period" text="No bills were recorded between these dates." /></div>
        @else
            <x-table caption="Purchases by supplier">
                <x-slot name="head">
                    <x-table.th>Supplier</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Bills</x-table.th>
                    <x-table.th num>Purchases</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Paid</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Still to pay</x-table.th>
                    <x-table.th num>Share</x-table.th>
                </x-slot>
                @foreach ($vendors as $vendor)
                    @php $left = (float) ($vendor->bills_sum_total ?? 0) - (float) ($vendor->bills_sum_amount_paid ?? 0); @endphp
                    <tr>
                        <td class="rpt-wrap"><a href="{{ route('vendors.show', $vendor) }}" class="tbl-link">{{ $vendor->name }}</a></td>
                        <td class="num hidden sm:table-cell">{{ number_format($vendor->bills_count) }}</td>
                        <td class="num font-medium">@fig($vendor->bills_sum_total)</td>
                        <td class="num hidden md:table-cell {{ \App\Support\Figure::tone($vendor->bills_sum_amount_paid) }}">@fig($vendor->bills_sum_amount_paid)</td>
                        <td class="num hidden sm:table-cell {{ \App\Support\Figure::tone($left) }}">@fig($left)</td>
                        <td class="num tbl-muted">{{ \App\Support\Figure::percent($vendor->bills_sum_total, $totalPurchases) }}</td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="num hidden sm:table-cell">{{ number_format($vendors->sum('bills_count')) }}</td>
                        <td class="num">@fig($totalPurchases)</td>
                        <td class="num hidden md:table-cell">@fig($totalPaid)</td>
                        <td class="num hidden sm:table-cell">@fig($owed)</td>
                        <td class="num">100%</td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
