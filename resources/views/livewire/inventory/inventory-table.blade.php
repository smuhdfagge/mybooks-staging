{{-- Stock list (tables plan T4): on hand, item by item, and what it is worth at cost. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $canBulk = $user->can('adjust inventory');
    $ids = $items->pluck('id')->map(fn ($id) => (string) $id)->all();
    $state = fn ($i) => (float) $i->on_hand <= 0 ? 'out' : ($i->reorder_level > 0 && (float) $i->on_hand <= (float) $i->reorder_level ? 'low' : 'ok');
    $badge = ['out' => ['overdue', 'Out of stock'], 'low' => ['partial', 'Running low'], 'ok' => ['paid', 'In stock']];
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or SKU" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            @if ($warehouses->isNotEmpty())<x-table.pick model="warehouse" label="Warehouse" :options="$warehouses" />@endif
            <x-table.pick model="category" label="Category" :options="$categories" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                <x-table.bulk-button action="reset_quantity" danger confirm="Set the ticked items' stock to zero{{ $warehouse !== '' ? ' in this warehouse' : '' }}? This posts a stock adjustment to the books.">Set stock to zero</x-table.bulk-button>
                <x-table.bulk-button action="disable_tracking" confirm="Stop counting stock for the ticked items? They will drop off this list.">Stop counting stock</x-table.bulk-button>
                <x-table.tick-all-matching :rows="$items" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($items->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No items match these filters" />
                @else
                    <x-table.empty title="No stock counted yet" text="Turn on stock counting for an item and its quantity shows here as you buy and sell.">
                        @can('create items')<a href="{{ route('items.create') }}" class="btn-new">New item</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Stock" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every item on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Item</x-table.th>
                    <x-table.th>Category</x-table.th>
                    <x-table.th field="on_hand" :sort="[$sortField, $sortDirection]" num>On hand</x-table.th>
                    <x-table.th num>Set aside</x-table.th>
                    <x-table.th num>Free to sell</x-table.th>
                    <x-table.th field="reorder_level" :sort="[$sortField, $sortDirection]" num>Reorder at</x-table.th>
                    <x-table.th field="stock_value" :sort="[$sortField, $sortDirection]" num>Value</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($items as $item)
                    @php $ticked = in_array((string) $item->id, $selectedItems, true); $s = $state($item); $free = (float) $item->on_hand - (float) $item->reserved; @endphp
                    <tr wire:key="inv-{{ $item->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$item->id" :label="$item->name" />@endif
                        <td class="max-w-[20rem]">
                            <a href="{{ route('inventory.show', $item) }}" class="tbl-link block truncate">{{ $item->name }}</a>
                            @if ($item->sku)<div class="text-xs tbl-muted">{{ $item->sku }}</div>@endif
                        </td>
                        <td class="max-w-[12rem] truncate {{ $item->category ? 'tbl-muted' : 'tbl-zero' }}">{{ $item->category?->name ?? '—' }}</td>
                        <td class="num {{ $s === 'ok' ? '' : 'tbl-late' }}">{{ $qty($item->on_hand) }}</td>
                        <td class="num {{ (float) $item->reserved > 0 ? '' : 'tbl-zero' }}">{{ (float) $item->reserved > 0 ? $qty($item->reserved) : '—' }}</td>
                        <td class="num">{{ $qty($free) }}</td>
                        <td class="num {{ $item->reorder_level > 0 ? 'tbl-muted' : 'tbl-zero' }}">{{ $item->reorder_level > 0 ? $qty($item->reorder_level) : '—' }}</td>
                        <td class="num {{ (float) $item->stock_value != 0 ? '' : 'tbl-zero' }}">{{ (float) $item->stock_value != 0 ? $money($item->stock_value) : '—' }}</td>
                        <td><x-status-badge :status="$badge[$s][0]" :label="$badge[$s][1]" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$item->name">
                                <x-table.menu-item :href="route('inventory.show', $item)">{{ $user->can('adjust inventory') ? 'View or adjust' : 'View' }}</x-table.menu-item>
                                <x-table.menu-item :href="route('inventory.history', $item)">Stock history</x-table.menu-item>
                                @can('view items')<x-table.menu-item :href="route('items.show', $item)">Item details</x-table.menu-item>@endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="6">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'item' : 'items' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->value) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Stock">
                @foreach ($items as $item)
                    @php $s = $state($item); @endphp
                    <li wire:key="inv-card-{{ $item->id }}">
                        <x-table.card :href="route('inventory.show', $item)" :title="$item->name" :amount="$qty($item->on_hand).($item->unit && $item->unit !== 'each' ? ' '.$item->unit : '')"
                            :meta="collect([$item->sku, (float) $item->stock_value != 0 ? \App\Support\Money::format($item->stock_value) : null])->filter()->implode(' · ')" :tone="$s === 'ok' ? 'muted' : 'bad'">
                            @if ($s !== 'ok')
                                <x-slot name="badge"><x-status-badge :status="$badge[$s][0]" :label="$badge[$s][1]" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Stock value {{ \App\Support\Money::format($totals->value) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$items" />
</div>
