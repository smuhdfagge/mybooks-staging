<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                @if($item->image_path)
                    <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->name }}" class="h-14 w-14 rounded-lg object-cover ring-2 ring-gray-200 dark:ring-gray-700">
                @else
                    <div class="h-14 w-14 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center ring-2 ring-gray-200 dark:ring-gray-700">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                @endif
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ $item->name }}
                    </h2>
                    @if($item->sku)
                        <p class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ $item->sku }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('items.edit', $item) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
                <a href="{{ route('items.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Selling Price</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($item->selling_price, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Cost Price</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($item->cost_price ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Rate</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $item->tax_rate ?? 0 }}%</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Profit Margin</p>
                            @php
                                $margin = $item->selling_price > 0 && $item->cost_price > 0 
                                    ? (($item->selling_price - $item->cost_price) / $item->selling_price * 100) 
                                    : 0;
                            @endphp
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($margin, 1) }}%</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Item Details -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Item Details</h3>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 capitalize">{{ $item->type }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item->category->name ?? 'Uncategorized' }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Unit</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item->unit ?? 'N/A' }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="mt-1">
                                        @if($item->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400">Active</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-400">Inactive</span>
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Taxable</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item->is_taxable ? 'Yes' : 'No' }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Track Inventory</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item->track_inventory ? 'Yes' : 'No' }}</dd>
                                </div>

                                @if($item->description)
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item->description }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Inventory Information -->
                    @if($item->track_inventory)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Inventory Status</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">On Hand</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $item->inventory->quantity ?? 0 }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Reserved</p>
                                    <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ $item->inventory->reserved_quantity ?? 0 }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Available</p>
                                    <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ ($item->inventory->quantity ?? 0) - ($item->inventory->reserved_quantity ?? 0) }}</p>
                                </div>
                            </div>

                            @if(($byWarehouse ?? collect())->isNotEmpty())
                                <div class="mt-4 -mx-6">@include('inventory._warehouse-split')</div>
                            @endif

                            @if($item->reorder_level && ($item->inventory->quantity ?? 0) <= $item->reorder_level)
                            <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                                <div class="flex">
                                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Low Stock Warning</p>
                                        <p class="text-sm text-yellow-700 dark:text-yellow-400">Stock is at or below reorder level ({{ $item->reorder_level }})</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Quick Actions & Info -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Quick Actions</h3>
                            <div class="space-y-3">
                                <a href="{{ route('invoices.create') }}?item_id={{ $item->id }}" class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Invoice
                                </a>
                                <a href="{{ route('items.edit', $item) }}" class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Edit Item
                                </a>
                                <form action="{{ route('items.destroy', $item) }}" method="POST" data-confirm="Are you sure you want to delete this item?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete Item
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Item Info</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item->created_at->format('M d, Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item->updated_at->format('M d, Y') }}</dd>
                                </div>
                                @if($item->reorder_level)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reorder Level</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item->reorder_level }} units</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
