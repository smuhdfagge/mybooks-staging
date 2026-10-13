{{-- Items list (tables plan T4). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $canBulk = $user->canAny(['edit items', 'delete items']);
    $ids = $items->pluck('id')->map(fn ($id) => (string) $id)->all();
    $low = fn ($i) => $i->track_inventory && $i->reorder_level > 0 && (float) $i->on_hand <= (float) $i->reorder_level;
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name, SKU or description" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.pick model="category" label="Category" :options="$categories" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit items')
                    <x-table.bulk-button action="activate">Make active</x-table.bulk-button>
                    <x-table.bulk-button action="deactivate" confirm="Make the ticked items inactive? They stay in your records but drop out of pick lists.">Make inactive</x-table.bulk-button>
                @endcan
                @can('delete items')<x-table.bulk-button action="delete" danger confirm="Delete the ticked items? Items on invoices or bills are skipped.">Delete</x-table.bulk-button>@endcan
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
                    <x-table.empty title="No items yet" text="Add the goods and services you sell or buy, so invoices and bills fill in prices for you.">
                        @can('create items')<a href="{{ route('items.create') }}" class="btn-new">New item</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Items" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every item on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th field="sku" :sort="[$sortField, $sortDirection]">SKU</x-table.th>
                    <x-table.th>Category</x-table.th>
                    <x-table.th field="selling_price" :sort="[$sortField, $sortDirection]" num>Selling price</x-table.th>
                    <x-table.th field="cost_price" :sort="[$sortField, $sortDirection]" num>Cost</x-table.th>
                    <x-table.th field="on_hand" :sort="[$sortField, $sortDirection]" num>In stock</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($items as $item)
                    @php $ticked = in_array((string) $item->id, $selectedItems, true); @endphp
                    <tr wire:key="it-{{ $item->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$item->id" :label="$item->name" />@endif
                        <td class="max-w-[20rem]">
                            <a href="{{ route('items.show', $item) }}" class="tbl-link block truncate">{{ $item->name }}</a>
                            @if ($item->type === 'service')<div class="text-xs tbl-muted">Service</div>@endif
                        </td>
                        <td class="{{ $item->sku ? 'tbl-muted' : 'tbl-zero' }}">{{ $item->sku ?: '—' }}</td>
                        <td class="max-w-[12rem] truncate {{ $item->category ? 'tbl-muted' : 'tbl-zero' }}">{{ $item->category?->name ?? '—' }}</td>
                        <td class="num">{{ $money($item->selling_price) }}</td>
                        <td class="num {{ (float) $item->cost_price > 0 ? '' : 'tbl-zero' }}">{{ (float) $item->cost_price > 0 ? $money($item->cost_price) : '—' }}</td>
                        <td class="num {{ ! $item->track_inventory ? 'tbl-zero' : ($low($item) ? 'tbl-late' : '') }}">{{ $item->track_inventory ? $qty($item->on_hand).($item->unit && $item->unit !== 'each' ? ' '.$item->unit : '') : '—' }}</td>
                        <td><x-status-badge :status="$item->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$item->name">
                                <x-table.menu-item :href="route('items.show', $item)">View</x-table.menu-item>
                                @can('edit items')<x-table.menu-item :href="route('items.edit', $item)">Edit</x-table.menu-item>@endcan
                                @if ($item->track_inventory && $user->can('view inventory'))
                                    <x-table.menu-item :href="route('inventory.show', $item)">Stock and adjustments</x-table.menu-item>
                                    <x-table.menu-item :href="route('inventory.history', $item)">Stock history</x-table.menu-item>
                                @endif
                                @can('delete items')
                                    <x-table.menu-item wire="deleteOne({{ $item->id }})" :confirm="'Delete '.$item->name.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="8">Total of {{ number_format($items->total()) }} {{ $items->total() == 1 ? 'item' : 'items' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Items">
                @foreach ($items as $item)
                    <li wire:key="it-card-{{ $item->id }}">
                        <x-table.card :href="route('items.show', $item)" :title="$item->name" :amount="\App\Support\Money::format($item->selling_price)"
                            :meta="collect([$item->sku, $item->track_inventory ? $qty($item->on_hand).' in stock' : ($item->type === 'service' ? 'Service' : null)])->filter()->implode(' · ')"
                            :tone="$low($item) ? 'bad' : 'muted'">
                            @if (! $item->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                            @if ($low($item))
                                <x-slot name="alert">Running low: reorder at {{ $qty($item->reorder_level) }}</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$items" />
</div>
