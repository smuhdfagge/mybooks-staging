{{-- Warehouses (tables plan T4): a short list, so a plain page with the shared table. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Warehouses" :description="'The places you keep stock. Documents use the default warehouse unless you choose another. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="actions">
                @can('create items')
                    <a href="{{ route('warehouses.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New warehouse
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        <x-table caption="Warehouses" class="hidden md:block">
            <x-slot name="head">
                <x-table.th>Warehouse</x-table.th>
                <x-table.th>Code</x-table.th>
                <x-table.th>Address</x-table.th>
                <x-table.th num>Items in stock</x-table.th>
                <x-table.th num>Stock value</x-table.th>
                <x-table.th>Status</x-table.th>
                <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
            </x-slot>
            @foreach ($warehouses as $warehouse)
                @php $row = $totals->get($warehouse->id); @endphp
                <tr>
                    <td>
                        <a href="{{ route('warehouses.show', $warehouse) }}" class="tbl-link">{{ $warehouse->name }}</a>
                        @if ($warehouse->is_default)<div class="text-xs tbl-muted">Default</div>@endif
                    </td>
                    <td class="tbl-muted">{{ $warehouse->code }}</td>
                    <td class="max-w-[20rem] truncate {{ $warehouse->address ? 'tbl-muted' : 'tbl-zero' }}">{{ $warehouse->address ?: '—' }}</td>
                    <td class="num">{{ number_format((int) ($row->items_in_stock ?? 0)) }}</td>
                    <td class="num">{{ $money($row->stock_value ?? 0) }}</td>
                    <td><x-status-badge :status="$warehouse->is_active ? 'active' : 'inactive'" :label="$warehouse->is_active ? 'In use' : 'Not in use'" /></td>
                    <td class="tbl-menu">
                        <x-table.dropdown :sr-label="'Actions for '.$warehouse->name">
                            <x-table.menu-item :href="route('warehouses.show', $warehouse)">View</x-table.menu-item>
                            @can('edit items')<x-table.menu-item :href="route('warehouses.edit', $warehouse)">Edit</x-table.menu-item>@endcan
                            <x-table.menu-item :href="route('inventory.index', ['warehouse' => $warehouse->id])">Stock here</x-table.menu-item>
                        </x-table.dropdown>
                    </td>
                </tr>
            @endforeach
            <x-slot name="foot">
                <tr>
                    <td colspan="4">All warehouses</td>
                    <td class="num">{{ $money($totals->sum('stock_value')) }}</td>
                    <td colspan="2"></td>
                </tr>
            </x-slot>
        </x-table>

        <ul class="space-y-2 md:hidden" aria-label="Warehouses">
            @foreach ($warehouses as $warehouse)
                @php $row = $totals->get($warehouse->id); @endphp
                <li>
                    <x-table.card :href="route('warehouses.show', $warehouse)" :title="$warehouse->name" :amount="\App\Support\Money::format($row->stock_value ?? 0)"
                        :meta="$warehouse->code.' · '.number_format((int) ($row->items_in_stock ?? 0)).' items in stock'.($warehouse->is_default ? ' · Default' : '')">
                        @if (! $warehouse->is_active)
                            <x-slot name="badge"><x-status-badge status="inactive" label="Not in use" /></x-slot>
                        @endif
                    </x-table.card>
                </li>
            @endforeach
        </ul>

        <x-table.footer :rows="$warehouses" links />

        @if (($inTransitValue ?? 0) > 0)
            <p class="text-sm text-gray-700 dark:text-gray-300">Also on the road between warehouses: <a href="{{ route('stock-transfers.index', ['status' => 'in_transit']) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">@money($inTransitValue) in transit</a>.</p>
        @endif
        <p class="text-xs text-gray-600 dark:text-gray-400">Stock value is the quantity on hand at each warehouse's average cost.</p>
    </div>
</x-app-layout>
