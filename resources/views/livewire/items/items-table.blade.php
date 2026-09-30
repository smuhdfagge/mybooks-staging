<div class="relative">
    <x-table-loading />
    <!-- Flash Messages -->
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Filters -->
    <div class="mb-4 sm:mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
            <!-- Search -->
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="search" class="form-label">Search</label>
                <input type="text" id="search" wire:model.live.debounce.300ms="search" 
                    placeholder="Search items..."
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
            
            <!-- Type Filter -->
            <div>
                <label for="type" class="form-label">Type</label>
                <select id="type" wire:model.live="type" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">All Types</option>
                    <option value="product">Product</option>
                    <option value="service">Service</option>
                </select>
            </div>
            
            <!-- Category Filter -->
            <div x-data="{
                open: false,
                search: '',
                selected: @entangle('category').live,
                categories: {{ $categories->map(fn($c) => ['id' => (string) $c->id, 'name' => $c->name])->values()->toJson() }},
                get filtered() {
                    if (!this.search) return this.categories;
                    return this.categories.filter(c => c.name.toLowerCase().includes(this.search.toLowerCase()));
                },
                get selectedName() {
                    if (!this.selected) return 'All Categories';
                    const found = this.categories.find(c => c.id === String(this.selected));
                    return found ? found.name : 'All Categories';
                },
                choose(id) {
                    this.selected = id;
                    this.open = false;
                    this.search = '';
                }
            }" @click.outside="open = false; search = ''" class="relative">
                <label class="form-label">Category</label>
                <button type="button" @click="open = !open"
                    class="w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm text-left px-3 py-2 bg-white flex items-center justify-between">
                    <span x-text="selectedName" class="truncate"></span>
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-cloak x-transition.opacity class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg">
                    <div class="p-2">
                        <input type="text" x-model="search" placeholder="Search categories..." 
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white text-sm px-2 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                            @click.stop>
                    </div>
                    <ul class="max-h-48 overflow-y-auto py-1">
                        <li @click="choose('')" class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 dark:hover:bg-gray-600 dark:text-gray-200"
                            :class="{ 'bg-blue-50 dark:bg-gray-600 font-medium': !selected }">All Categories</li>
                        <template x-for="cat in filtered" :key="cat.id">
                            <li @click="choose(cat.id)" class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 dark:hover:bg-gray-600 dark:text-gray-200"
                                :class="{ 'bg-blue-50 dark:bg-gray-600 font-medium': selected === cat.id }"
                                x-text="cat.name"></li>
                        </template>
                        <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No categories found</li>
                    </ul>
                </div>
            </div>
            
            <!-- Per Page -->
            <div>
                <label for="perPage" class="form-label">Per Page</label>
                <select id="perPage" wire:model.live="perPage" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <!-- Bulk Actions -->
            <x-bulk-actions :actions="['activate' => 'Activate', 'deactivate' => 'Deactivate', 'delete' => 'Delete']" :selectedCount="count($selectedItems)" />
        </div>
    </div>

    <!-- Table Container with horizontal scroll -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">
                            <input type="checkbox" wire:model.live="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                        </th>
                        <x-sort-header field="name" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Name</x-sort-header>
                        <x-sort-header field="sku" :sort-field="$sortField" :sort-direction="$sortDirection" class="hidden sm:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">SKU</x-sort-header>
                        <th scope="col" class="hidden md:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th scope="col" class="hidden lg:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                        <x-sort-header field="selling_price" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Price</x-sort-header>
                        <th scope="col" class="hidden sm:table-cell px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($items as $item)
                        <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $item->id }}"
                                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item->name }}</div>
                                @if($item->description)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px] sm:max-w-xs">{{ $item->description }}</div>
                                @endif
                                <!-- Mobile: Show SKU under name -->
                                <div class="sm:hidden text-xs text-gray-500 dark:text-gray-400 mt-1">SKU: {{ $item->sku ?? '-' }}</div>
                            </td>
                            <td class="hidden sm:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->sku ?? '-' }}
                            </td>
                            <td class="hidden md:table-cell px-4 sm:px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $item->type === 'product' ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' : 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100' }}">
                                    {{ ucfirst($item->type) }}
                                </span>
                            </td>
                            <td class="hidden lg:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->category?->name ?? '-' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                {{ number_format($item->selling_price, 2) }}
                            </td>
                            <td class="hidden sm:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-center">
                                @if($item->type === 'product' && $item->track_inventory)
                                    <span class="{{ ($item->inventory?->quantity ?? 0) <= ($item->reorder_level ?? 0) ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                        {{ $item->inventory?->quantity ?? 0 }}
                                    </span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $item->is_active ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300' }}">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-1 sm:space-x-2">
                                    <a href="{{ route('items.show', $item) }}" class="p-1 text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="View" aria-label="View">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                    <a href="{{ route('items.edit', $item) }}" class="p-1 text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300" title="Edit" aria-label="Edit">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <button wire:click="deleteItem({{ $item->id }})" 
                                            wire:confirm="Are you sure you want to delete this item?"
                                            class="p-1 text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Delete" aria-label="Delete">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 sm:px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No items found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new item.</p>
                                <div class="mt-6">
                                    <a href="{{ route('items.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Add Item
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($items->hasPages())
            <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
