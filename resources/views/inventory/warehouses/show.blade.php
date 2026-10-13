<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$warehouse->name" :description="$warehouse->code.($warehouse->address ? ' · '.$warehouse->address : '')">
            @if ($warehouse->is_default || ! $warehouse->is_active)
                <x-slot name="badge">
                    @if ($warehouse->is_default)<x-status-badge status="sent" label="Default" />@endif
                    @unless ($warehouse->is_active)<x-status-badge status="inactive" label="Not in use" />@endunless
                </x-slot>
            @endif
            @can('delete items')
                @unless ($warehouse->is_default)
                    <x-slot name="more">
                        <x-table.menu-item danger :post="route('warehouses.destroy', $warehouse)" method="DELETE" confirm="Delete this warehouse?">Delete</x-table.menu-item>
                    </x-slot>
                @endunless
            @endcan
            @can('edit items')
                <x-slot name="actions"><a href="{{ route('warehouses.edit', $warehouse) }}" class="btn-secondary">Edit</a></x-slot>
            @endcan
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Stock value</p>
                    <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">@money($stockValue)</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Items in stock</p>
                    <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $inventories->total() }}</p>
                </x-card>
                @if($warehouse->contact_person || $warehouse->phone)
                    <x-card class="p-4 col-span-2 sm:col-span-1">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Contact</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $warehouse->contact_person }} {{ $warehouse->phone }}</p>
                    </x-card>
                @endif
            </div>

            @if(($inTransit ?? collect())->isNotEmpty())
                @php
                    $incoming = $inTransit->where('to_warehouse_id', $warehouse->id);
                    $outgoing = $inTransit->where('from_warehouse_id', $warehouse->id);
                @endphp
                <x-card title="In transit">
                    <div class="p-4 sm:p-6 pt-2 space-y-3 text-sm">
                        <p class="text-gray-600 dark:text-gray-400">Goods on the road are not counted in this warehouse's stock above, but they are still your stock.
                            @if($incoming->isNotEmpty()) Coming in: <strong class="text-gray-900 dark:text-gray-100">@money($incoming->sum(fn ($t) => $t->shippedCost()))</strong>.@endif
                            @if($outgoing->isNotEmpty()) Going out: <strong class="text-gray-900 dark:text-gray-100">@money($outgoing->sum(fn ($t) => $t->shippedCost()))</strong>.@endif
                        </p>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($inTransit as $transfer)
                                <li class="py-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                    <div>
                                        <a href="{{ route('stock-transfers.show', $transfer) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $transfer->transfer_number }}</a>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ $transfer->to_warehouse_id === $warehouse->id ? 'coming from '.$transfer->fromWarehouse?->name : 'going to '.$transfer->toWarehouse?->name }},
                                            sent {{ $transfer->transfer_date?->format('j M Y') }}
                                        </span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                                            {{ $transfer->items->map(fn ($l) => rtrim(rtrim(number_format((float) $l->quantity, 4), '0'), '.').' '.$l->item?->name)->implode(', ') }}
                                        </span>
                                    </div>
                                    <span class="text-gray-900 dark:text-gray-100 whitespace-nowrap">@money($transfer->shippedCost())</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </x-card>
            @endif

            <x-card title="Stock in this warehouse">
                @if($inventories->isEmpty())
                    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">Nothing in stock here yet. Choose this warehouse on a bill or a stock adjustment to bring goods in.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <x-table caption="Stock in this warehouse">
<x-slot name="head">
                                    <x-table.th>Item</x-table.th>
                                    <x-table.th num>On hand</x-table.th>
                                    <x-table.th num class="hidden sm:table-cell">Reserved</x-table.th>
                                    <x-table.th num class="hidden sm:table-cell">Available</x-table.th>
                                    <x-table.th num class="hidden md:table-cell">Average cost</x-table.th>
                                    <x-table.th num>Value</x-table.th>
</x-slot>

                                @foreach($inventories as $row)
                                    <tr>
                                        <td>
                                            <a href="{{ route('inventory.show', $row->item_id) }}" class="text-brand-600 dark:text-brand-300 font-medium">{{ $row->item->name ?? '—' }}</a>
                                            @if($row->item?->sku)<span class="block text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $row->item->sku }}</span>@endif
                                        </td>
                                        <td class="num">{{ rtrim(rtrim(number_format((float) $row->quantity, 4), '0'), '.') }}</td>
                                        <td class="num text-amber-700 dark:text-amber-300 hidden sm:table-cell">{{ rtrim(rtrim(number_format((float) $row->reserved_quantity, 4), '0'), '.') }}</td>
                                        <td class="num hidden sm:table-cell">{{ rtrim(rtrim(number_format((float) $row->quantity - (float) $row->reserved_quantity, 4), '0'), '.') }}</td>
                                        <td class="num hidden md:table-cell">@fig($row->unit_cost, false)</td>
                                        <td class="num">@fig((float) $row->quantity * (float) $row->unit_cost, false)</td>
                                    </tr>
                                @endforeach
</x-table>
                    </div>
                    <div class="p-4">{{ $inventories->links() }}</div>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
