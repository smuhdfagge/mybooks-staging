{{-- Stock summary (tables plan T6): what is in stock now and what it is worth at cost. Amounts in ₦. --}}
@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0';
    $place = $warehouseId ? $warehouses->firstWhere('id', $warehouseId)?->name : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Stock summary" :description="'What is in stock'.($place ? ' in '.$place : '').' now, and what it is worth at cost. Amounts in ₦.'"
            export="inventory-summary" :filters="['warehouse_id' => $warehouseId]">
            <x-slot name="more">
                @can('view items')<x-table.menu-item :href="route('inventory.index', ['stock' => 'low'])">Items running low</x-table.menu-item>@endcan
                <x-table.menu-item :href="route('reports.sales-by-item')">Sales by item</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Stock summary" :period="'At '.now()->format('j M Y, H:i').($place ? ' · '.$place : '')">
        @if ($warehouses->count() > 1)
            {{-- Warehouse filter (session 12) --}}
            <x-report.filters :action="route('reports.inventory-summary')" button="Show">
                <x-report.pick name="warehouse_id" label="Warehouse" :options="$warehouses->pluck('name', 'id')" :value="$warehouseId" all="All warehouses" />
            </x-report.filters>
        @endif

        <x-report.stats :cols="3">
            <x-report.stat label="Stock value" :value="\App\Support\Figure::show($totalValue)" :hint="$inTransit ? 'Includes goods in transit' : null" />
            <x-report.stat label="Items tracked" :value="number_format($totalItems)" />
            <x-report.stat label="Running low or out" :value="number_format($lowStockItems)" :tone="$lowStockItems > 0 ? 'bad' : null" />
        </x-report.stats>

        @if ($items->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No stock items" text="Items show here once they track stock." /></div>
        @else
            <x-table caption="Stock summary">
                <x-slot name="head">
                    <x-table.th>Item</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Cost price</x-table.th>
                    <x-table.th num>In stock</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Reorder at</x-table.th>
                    <x-table.th num>Value</x-table.th>
                    <x-table.th class="hidden sm:table-cell">Status</x-table.th>
                </x-slot>
                @foreach ($items as $item)
                    @php [$tone, $label] = $item->stock_quantity <= 0 ? ['overdue', 'Out of stock'] : ($item->is_low_stock ? ['pending', 'Running low'] : ['completed', 'In stock']); @endphp
                    <tr>
                        <td class="rpt-wrap">
                            <a href="{{ route('items.show', $item) }}" class="tbl-link">{{ $item->name }}</a>
                            @if ($item->sku)<span class="text-xs tbl-muted">{{ $item->sku }}</span>@endif
                        </td>
                        <td class="num hidden md:table-cell tbl-muted">@fig($item->cost_price)</td>
                        <td class="num {{ $item->stock_quantity <= 0 ? 'tbl-late' : '' }}">{{ $qty($item->stock_quantity) }}</td>
                        <td class="num hidden md:table-cell {{ $item->reorder_level ? 'tbl-muted' : 'tbl-zero' }}">{{ $item->reorder_level ? $qty($item->reorder_level) : '—' }}</td>
                        <td class="num font-medium">@fig($item->stock_value)</td>
                        <td class="hidden sm:table-cell"><x-status-badge :status="$tone" :label="$label" /></td>
                    </tr>
                @endforeach
                @if ($inTransit)
                    {{-- Shipped between warehouses, not yet received (session 13). --}}
                    <tr data-in-transit>
                        <td class="rpt-wrap">
                            <a href="{{ route('stock-transfers.index', ['status' => 'in_transit']) }}" class="tbl-link">Goods in transit</a> between warehouses
                            <span class="block text-xs tbl-muted">Cost when sent: {{ number_format($inTransit['cost'], 2) }}</span>
                        </td>
                        <td class="hidden md:table-cell"></td>
                        <td class="num">{{ $qty($inTransit['quantity']) }}</td>
                        <td class="hidden md:table-cell"></td>
                        <td class="num font-medium">@fig($inTransit['value'])</td>
                        <td class="hidden sm:table-cell"></td>
                    </tr>
                @endif
                <x-slot name="foot">
                    <tr>
                        <td>Total stock value</td>
                        <td class="hidden md:table-cell"></td>
                        <td></td>
                        <td class="hidden md:table-cell"></td>
                        <td class="num">@fig($totalValue)</td>
                        <td class="hidden sm:table-cell"></td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
