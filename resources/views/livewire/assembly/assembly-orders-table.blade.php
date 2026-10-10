{{-- Assembly orders list (tables plan T4). --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $st = fn ($o) => $o->status instanceof \BackedEnum ? $o->status->value : $o->status;
    $label = fn ($o) => $st($o) === 'completed' ? 'Done' : null;
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, item or bill of materials" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="kind" label="Type" :options="$kinds" />
            @if ($warehouses->isNotEmpty())<x-table.pick model="warehouse" label="Warehouse" :options="$warehouses" />@endif
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($orders->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No assembly orders match these filters" />
                @else
                    <x-table.empty title="No assembly orders yet" text="Make finished goods from their parts using a bill of materials.">
                        @can('adjust inventory')<a href="{{ route('assembly-orders.create') }}" class="btn-new">New build</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Assembly orders" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="order_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th field="assembly_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th>Item</x-table.th>
                    <x-table.th num>Quantity</x-table.th>
                    <x-table.th field="total_cost" :sort="[$sortField, $sortDirection]" num>Cost</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($orders as $order)
                    @php $done = $st($order) === 'completed'; $q = $done ? (float) $order->quantity_made : $order->plannedQuantity(); @endphp
                    <tr wire:key="asm-{{ $order->id }}">
                        <td>
                            <a href="{{ route('assembly-orders.show', $order) }}" class="tbl-link">{{ $order->order_number }}</a>
                            @if ($order->isBreakdown())<div class="text-xs tbl-muted">Break-down</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $date($order->assembly_date) }}</td>
                        <td class="max-w-[18rem]">
                            <span class="block truncate">{{ $order->billOfMaterial?->item?->name ?? '—' }}</span>
                            @if ($order->billOfMaterial)<span class="block truncate text-xs tbl-muted">{{ $order->billOfMaterial->label() }}</span>@endif
                        </td>
                        <td class="num">{{ $qty($q) }} {{ $order->billOfMaterial?->item?->unit !== 'each' ? $order->billOfMaterial?->item?->unit : '' }}</td>
                        <td class="num {{ $done ? '' : 'tbl-zero' }}">{{ $done ? $money($order->total_cost) : '—' }}</td>
                        <td><x-status-badge :status="$st($order)" :label="$label($order)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$order->order_number">
                                <x-table.menu-item :href="route('assembly-orders.show', $order)">{{ $st($order) === 'draft' ? 'View or complete' : 'View' }}</x-table.menu-item>
                                <x-table.menu-item :href="route('assembly-orders.print', $order)" new-tab>Print</x-table.menu-item>
                                @if ($st($order) === 'draft')<x-table.menu-item :href="route('assembly-orders.edit', $order)">Edit</x-table.menu-item>@endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'order' : 'orders' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif. Cost of those done:</td>
                        <td class="num">{{ $money($totals->cost) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Assembly orders">
                @foreach ($orders as $order)
                    <li wire:key="asm-card-{{ $order->id }}">
                        <x-table.card :href="route('assembly-orders.show', $order)" :title="$order->billOfMaterial?->item?->name ?? $order->order_number"
                            :amount="$st($order) === 'completed' ? \App\Support\Money::format($order->total_cost) : null"
                            :meta="$order->order_number.' · '.$date($order->assembly_date)">
                            <x-slot name="badge"><x-status-badge :status="$st($order)" :label="$label($order)" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$orders" />
</div>
