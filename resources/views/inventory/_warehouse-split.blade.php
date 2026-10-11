{{-- Stock per warehouse for one item (session 12). $byWarehouse: Inventory rows with warehouse. --}}
@if($byWarehouse->isNotEmpty())
@php
    // Shipped between warehouses, not yet received (session 13): in neither warehouse, still our stock.
    $inTransit = isset($item) ? \App\Models\StockTransfer::inTransitQuantity($item) : 0;
@endphp
<x-card class="mb-6" title="Stock by warehouse">
    <div class="mt-3 pb-2 overflow-x-auto">
        <x-table caption="Stock by warehouse">
<x-slot name="head">
                    <x-table.th>Warehouse</x-table.th>
                    <x-table.th num>On hand</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Reserved</x-table.th>
                    <x-table.th num>Available</x-table.th>
</x-slot>

                @foreach($byWarehouse as $row)
                    <tr>
                        <td>
                            @if($row->warehouse && Route::has('warehouses.show'))
                                <a href="{{ route('warehouses.show', $row->warehouse) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $row->warehouse->name }}</a>
                            @else
                                {{ $row->warehouse->name ?? '—' }}
                            @endif
                            @if($row->warehouse?->is_default)<span class="ml-1 text-xs text-gray-500 dark:text-gray-400">(default)</span>@endif
                        </td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $row->quantity, 4), '0'), '.') }}</td>
                        <td class="num text-amber-700 dark:text-amber-300 hidden sm:table-cell">{{ rtrim(rtrim(number_format((float) $row->reserved_quantity, 4), '0'), '.') }}</td>
                        <td class="num text-green-700 dark:text-green-400">{{ rtrim(rtrim(number_format((float) $row->quantity - (float) $row->reserved_quantity, 4), '0'), '.') }}</td>
                    </tr>
                @endforeach
                @if($inTransit > 0)
                    <tr class="bg-yellow-50/60">
                        <td>
                            <a href="{{ route('stock-transfers.index', ['status' => 'in_transit']) }}" class="text-brand-600 dark:text-brand-300 hover:underline">In transit</a>
                            <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">(on the road)</span>
                        </td>
                        <td class="num">{{ rtrim(rtrim(number_format($inTransit, 4), '0'), '.') }}</td>
                        <td class="num hidden sm:table-cell">—</td>
                        <td class="num">—</td>
                    </tr>
                @endif
<x-slot name="foot">
                <tr>
                    <td>All warehouses</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $byWarehouse->sum('quantity'), 4), '0'), '.') }}</td>
                    <td class="num hidden sm:table-cell">{{ rtrim(rtrim(number_format((float) $byWarehouse->sum('reserved_quantity'), 4), '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $byWarehouse->sum('quantity') - (float) $byWarehouse->sum('reserved_quantity'), 4), '0'), '.') }}</td>
                </tr>
</x-slot>
</x-table>
        @if($inTransit > 0)
            <p class="px-6 pt-2 text-sm text-gray-600 dark:text-gray-400">Total stock including goods in transit: <strong class="text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format((float) $byWarehouse->sum('quantity') + $inTransit, 4), '0'), '.') }}</strong></p>
        @endif
    </div>
</x-card>
@endif
