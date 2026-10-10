{{-- Sales by item (tables plan T6): how much of each item sold in a period. Drafts and cancelled invoices are left out. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Sales by item" :description="'How much of each item sold from '.$from.' to '.$to.', biggest first. Amounts in ₦.'"
            export="sales-by-item" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.sales-by-customer', ['start_date' => $startDate, 'end_date' => $endDate])">Sales by customer</x-table.menu-item>
                <x-table.menu-item :href="route('reports.inventory-summary')">Stock summary</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Sales by item" :period="$from.' to '.$to">
        <x-report.filters :action="route('reports.sales-by-item')">
            <x-report.date name="start_date" label="From" :value="$startDate" />
            <x-report.date name="end_date" label="To" :value="$endDate" />
        </x-report.filters>

        <x-report.stats :cols="3">
            <x-report.stat label="Sales" :value="\App\Support\Figure::show($totalSales)" />
            <x-report.stat label="Quantity sold" :value="$qty($totalQuantity)" />
            <x-report.stat label="Items sold" :value="number_format($items->count())" />
        </x-report.stats>

        @if ($items->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No sales in this period" text="No items were sold on invoices between these dates." /></div>
        @else
            <x-table caption="Sales by item">
                <x-slot name="head">
                    <x-table.th>Item</x-table.th>
                    <x-table.th num>Quantity</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Average price</x-table.th>
                    <x-table.th num>Sales</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Share</x-table.th>
                </x-slot>
                @foreach ($items as $item)
                    <tr>
                        <td class="rpt-wrap">
                            <a href="{{ route('items.show', $item) }}" class="tbl-link">{{ $item->name }}</a>
                            @if ($item->sku)<span class="text-xs tbl-muted">{{ $item->sku }}</span>@endif
                        </td>
                        <td class="num">{{ $qty($item->quantity_sold) }}</td>
                        <td class="num hidden sm:table-cell tbl-muted">@fig($item->quantity_sold > 0 ? $item->total_sales / $item->quantity_sold : 0)</td>
                        <td class="num font-medium">@fig($item->total_sales)</td>
                        <td class="num hidden sm:table-cell tbl-muted">{{ \App\Support\Figure::percent($item->total_sales, $totalSales) }}</td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="num">{{ $qty($totalQuantity) }}</td>
                        <td class="hidden sm:table-cell"></td>
                        <td class="num">@fig($totalSales)</td>
                        <td class="num hidden sm:table-cell">100%</td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
