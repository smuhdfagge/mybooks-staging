<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Warehouses</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">The places you keep stock. Documents use the default warehouse unless you choose another.</p>
            </div>
            @can('create items')
                <a href="{{ route('warehouses.create') }}" class="btn-primary">New warehouse</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <x-card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Warehouse</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Address</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden sm:table-cell">Items in stock</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stock value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($warehouses as $warehouse)
                                @php($row = $totals->get($warehouse->id))
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('warehouses.show', $warehouse) }}" class="text-brand-600 dark:text-brand-300 font-medium">{{ $warehouse->name }}</a>
                                        <span class="ml-1 text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $warehouse->code }}</span>
                                        @if($warehouse->is_default)
                                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300">Default</span>
                                        @endif
                                        @unless($warehouse->is_active)
                                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Not in use</span>
                                        @endunless
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 hidden md:table-cell">{{ $warehouse->address ?: '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100 hidden sm:table-cell">{{ (int) ($row->items_in_stock ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100 whitespace-nowrap">@money($row->stock_value ?? 0)</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">All warehouses</td>
                                <td class="hidden md:table-cell"></td>
                                <td class="hidden sm:table-cell"></td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">@money($totals->sum('stock_value'))</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="p-4">{{ $warehouses->links() }}</div>
            </x-card>
            @if(($inTransitValue ?? 0) > 0)
                <p class="text-sm text-gray-700 dark:text-gray-300">Also on the road between warehouses: <a href="{{ route('stock-transfers.index', ['status' => 'in_transit']) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">@money($inTransitValue) in transit</a>.</p>
            @endif
            <p class="text-xs text-gray-500 dark:text-gray-400">Stock value is the quantity on hand at each warehouse's average cost.</p>
        </div>
    </div>
</x-app-layout>
