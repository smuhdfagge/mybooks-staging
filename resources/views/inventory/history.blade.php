<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $item->name }} - History
                </h2>
                @if($item->sku)
                    <p class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ $item->sku }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('inventory.show', $item) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Inventory
                </a>
                <a href="{{ route('inventory.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Current Stock Quick View -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-brand-100 dark:bg-brand-900 rounded-full p-3">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Stock</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $item->inventory->quantity ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->unit ?? 'units' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-accent-100 dark:bg-accent-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-accent-700 dark:text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Stock Value</p>
                            <p class="text-2xl font-bold text-accent-700 dark:text-accent-300">{{ number_format(($item->inventory->quantity ?? 0) * ($item->cost_price ?? 0), 2) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">at cost price</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-full p-3">
                            <svg class="w-6 h-6 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Stock In</p>
                            <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $history->where('type', 'in')->sum('quantity') + $history->where('type', 'purchase')->sum('quantity') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">all time</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 dark:bg-red-900 rounded-full p-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Stock Out</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-300">{{ $history->where('type', 'out')->sum('quantity') + $history->where('type', 'sale')->sum('quantity') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">all time</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Inventory History
                    </h3>

                    @if($warehouses->count() > 1)
                    <form method="GET" class="mb-4 flex flex-col sm:flex-row sm:items-end gap-3">
                        <div class="sm:w-64">
                            <x-field name="warehouse_id" label="Warehouse" type="select">
                                <option value="">All warehouses</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                                @endforeach
                            </x-field>
                        </div>
                        <button type="submit" class="btn-primary">Show</button>
                    </form>
                    @endif
                    
                    @if($history->count() > 0)
                    <div class="overflow-x-auto -mx-6">
                        <div class="inline-block min-w-full align-middle px-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                        @if($warehouses->count() > 1)
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Warehouse</th>
                                        @endif
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Notes</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">By</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($history as $record)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $record->created_at->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $record->created_at->format('h:i A') }}</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            @php
                                                // By direction: stock in green, stock out red, returns amber, moves navy.
                                                $typeColors = [
                                                    'in' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400',
                                                    'out' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                                                    'adjustment' => 'bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300',
                                                    'sale' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                                                    'purchase' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400',
                                                    'return' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
                                                    'transfer' => 'bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300',
                                                    'assembly' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                ];
                                                $typeLabels = [
                                                    'in' => 'Stock In',
                                                    'out' => 'Stock Out',
                                                    'adjustment' => 'Adjustment',
                                                    'sale' => 'Sale',
                                                    'purchase' => 'Purchase',
                                                    'return' => 'Return',
                                                    'transfer' => 'Transfer',
                                                    'assembly' => 'Assembly',
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$record->type] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                {{ $typeLabels[$record->type] ?? ucfirst($record->type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right">
                                            <span class="text-sm font-medium {{ $record->type === 'in' || $record->type === 'purchase' || $record->type === 'return' ? 'text-green-700 dark:text-green-400' : ($record->type === 'out' || $record->type === 'sale' ? 'text-red-600 dark:text-red-300' : 'text-gray-900 dark:text-gray-100') }}">
                                                @if($record->type === 'in' || $record->type === 'purchase' || $record->type === 'return')
                                                    +{{ $record->quantity }}
                                                @elseif($record->type === 'out' || $record->type === 'sale')
                                                    -{{ $record->quantity }}
                                                @elseif(in_array($record->type, ['transfer', 'assembly'], true))
                                                    {{ (float) $record->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $record->quantity, 4), '0'), '.') }}
                                                @else
                                                    {{ $record->quantity }}
                                                @endif
                                            </span>
                                        </td>
                                        @if($warehouses->count() > 1)
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $record->warehouse->name ?? '-' }}</td>
                                        @endif
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-900 dark:text-gray-100 max-w-xs truncate">@include('inventory._history-note', ['record' => $record])</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $record->createdBy->name ?? 'System' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $history->links() }}
                    </div>
                    @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No history</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No inventory adjustments have been made for this item.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
