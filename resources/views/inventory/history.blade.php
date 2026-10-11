{{-- Stock history for one item (tables plan T7): every move in and out, newest first. --}}
@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0';
    $in = ['in', 'purchase', 'return'];
    $out = ['out', 'sale'];
    $labels = ['in' => 'Stock in', 'out' => 'Stock out', 'adjustment' => 'Adjustment', 'sale' => 'Sale', 'purchase' => 'Purchase', 'return' => 'Return', 'transfer' => 'Transfer', 'assembly' => 'Assembly'];
    $tone = fn ($t) => in_array($t, $in, true) ? 'completed' : (in_array($t, $out, true) ? 'overdue' : 'draft');
    $onHand = (float) ($item->inventory->quantity ?? 0);
    $place = $warehouseId ? $warehouses->firstWhere('id', $warehouseId)?->name : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$item->name.': stock history'" :description="'Every move of '.$item->name.($item->sku ? ' ('.$item->sku.')' : '').' in and out'.($place ? ' of '.$place : '').', newest first.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('inventory.show', $item)">Item's stock</x-table.menu-item>
                <x-table.menu-item :href="route('inventory.index')">All stock</x-table.menu-item>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        @if ($warehouses->count() > 1)
            <x-report.filters :action="route('inventory.history', $item)" button="Show">
                <x-report.pick name="warehouse_id" label="Warehouse" :options="$warehouses->pluck('name', 'id')" :value="$warehouseId" all="All warehouses" />
            </x-report.filters>
        @endif

        <x-report.stats :cols="4">
            <x-report.stat label="In stock now" :value="$qty($onHand)" :hint="$item->unit ?: 'units'" />
            <x-report.stat label="Value at cost" :value="\App\Support\Figure::show($onHand * (float) ($item->cost_price ?? 0))" hint="Amounts in ₦" />
            <x-report.stat label="Taken in" :value="$qty($moved->qty_in)" hint="All time" tone="good" />
            <x-report.stat label="Sent out" :value="$qty($moved->qty_out)" hint="All time" />
        </x-report.stats>

        @if ($history->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No stock moves yet" text="Purchases, sales, transfers and adjustments of this item show here." /></div>
        @else
            <x-table caption="Stock history">
                <x-slot name="head">
                    <x-table.th>When</x-table.th>
                    <x-table.th>Move</x-table.th>
                    <x-table.th num>Quantity</x-table.th>
                    @if ($warehouses->count() > 1)
                        <x-table.th class="hidden sm:table-cell">Warehouse</x-table.th>
                    @endif
                    <x-table.th class="hidden md:table-cell">Note</x-table.th>
                    <x-table.th class="hidden lg:table-cell">By</x-table.th>
                </x-slot>
                @foreach ($history as $record)
                    @php
                        $sign = in_array($record->type, $in, true) ? '+' : (in_array($record->type, $out, true) ? '-' : ((float) $record->quantity > 0 && in_array($record->type, ['transfer', 'assembly'], true) ? '+' : ''));
                    @endphp
                    <tr>
                        <td class="tbl-muted">{{ $record->created_at->format('j M Y') }}<div class="text-xs">{{ $record->created_at->format('H:i') }}</div></td>
                        <td><x-status-badge :status="$tone($record->type)" :label="$labels[$record->type] ?? ucfirst($record->type)" /></td>
                        <td class="num font-medium {{ $sign === '+' ? 'text-green-800 dark:text-green-300' : ($sign === '-' ? 'tbl-late' : '') }}">{{ $sign }}{{ $qty($record->quantity) }}</td>
                        @if ($warehouses->count() > 1)
                            <td class="hidden sm:table-cell">{{ $record->warehouse->name ?? '—' }}</td>
                        @endif
                        <td class="rpt-wrap hidden md:table-cell max-w-[24rem]">@include('inventory._history-note', ['record' => $record])</td>
                        <td class="hidden lg:table-cell tbl-muted">{{ $record->createdBy->name ?? 'MyBooks' }}</td>
                    </tr>
                @endforeach
            </x-table>
            <x-table.footer :rows="$history" links />
        @endif
    </div>
</x-app-layout>
