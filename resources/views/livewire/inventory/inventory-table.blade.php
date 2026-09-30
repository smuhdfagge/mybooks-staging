<div class="relative">
    <x-table-loading />
    <!-- Flash Messages -->
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div>
            <label for="search" class="form-label">Search</label>
            <input wire:model.live.debounce.300ms="search" type="text" id="search" placeholder="Search items..."
                class="form-control">
        </div>
        
        <div>
            <label for="categoryFilter" class="form-label">Category</label>
            <select wire:model.live="categoryFilter" id="categoryFilter"
                class="form-control">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        
        <div>
            <label for="stockFilter" class="form-label">Stock Status</label>
            <select wire:model.live="stockFilter" id="stockFilter"
                class="form-control">
                <option value="">All Items</option>
                <option value="in_stock">In Stock</option>
                <option value="low_stock">Low Stock</option>
                <option value="out_of_stock">Out of Stock</option>
            </select>
        </div>

        <div>
            <label for="perPage" class="form-label">Per Page</label>
            <select wire:model.live="perPage" id="perPage"
                class="form-control">
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>

        <!-- Bulk Actions -->
        <x-bulk-actions :actions="['reset_quantity' => 'Reset Quantity', 'disable_tracking' => 'Disable Tracking']" :selectedCount="count($selectedItems)" />
    </div>

    <!-- Table -->
    <div class="overflow-x-auto -mx-6">
        <div class="inline-block min-w-full align-middle px-6">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">
                            <input type="checkbox" wire:model.live="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                        </th>
                        <x-sort-header field="name" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">Item</x-sort-header>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SKU</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">On Hand</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reserved</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Available</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reorder</th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($items as $item)
                        @php
                            $onHand = $item->inventory->quantity ?? 0;
                            $reserved = $item->inventory->reserved_quantity ?? 0;
                            $available = $onHand - $reserved;
                            $reorderLevel = $item->reorder_level ?? 0;
                            
                            $isLowStock = $reorderLevel > 0 && $onHand <= $reorderLevel;
                            $isOutOfStock = $onHand <= 0;
                        @endphp
                        <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors {{ $isOutOfStock ? 'bg-red-50 dark:bg-red-900/20' : ($isLowStock ? 'bg-yellow-50 dark:bg-yellow-900/20' : '') }}">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $item->id }}"
                                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $item->name }}</div>
                                @if($item->unit)
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Unit: {{ $item->unit }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-mono">
                                {{ $item->sku ?? '-' }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->category->name ?? 'Uncategorized' }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                                {{ number_format($onHand, 2) }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-orange-600 dark:text-orange-400">
                                {{ number_format($reserved, 2) }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-right font-medium {{ $available > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ number_format($available, 2) }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">
                                {{ $reorderLevel > 0 ? number_format($reorderLevel) : '-' }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                @if($isOutOfStock)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400">
                                        Out of Stock
                                    </span>
                                @elseif($isLowStock)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-400">
                                        Low Stock
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400">
                                        In Stock
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('inventory.show', $item) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 mr-3">Adjust</a>
                                <a href="{{ route('inventory.history', $item) }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">History</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <p class="mt-2">No inventory items found.</p>
                                <p class="text-sm">Make sure items have "Track Inventory" enabled.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $items->links() }}
    </div>
</div>
