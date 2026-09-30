<div class="relative">
    <x-table-loading />
    <!-- Flash Messages -->
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Assets</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalAssets }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Assets</div>
            <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $activeAssets }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Cost</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalCost, 2) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Book Value</div>
            <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ number_format($totalValue, 2) }}</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="form-label">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search assets..." 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <select wire:model.live="categoryFilter" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Per Page</label>
                    <select wire:model.live="perPage" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <!-- Bulk Actions -->
                <x-bulk-actions :actions="['activate' => 'Activate', 'dispose' => 'Dispose', 'delete' => 'Delete']" :selectedCount="count($selectedItems)" />
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">
                            <input type="checkbox" wire:model.live="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                        </th>
                        <x-sort-header field="asset_number" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Asset #</x-sort-header>
                        <x-sort-header field="name" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Name</x-sort-header>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                        <x-sort-header field="purchase_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Purchase Date</x-sort-header>
                        <x-sort-header field="purchase_cost" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Cost</x-sort-header>
                        <x-sort-header field="book_value" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Book Value</x-sort-header>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($assets as $asset)
                        <tr wire:key="asset-{{ $asset->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $asset->id }}"
                                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600 dark:text-blue-400">
                                <a href="{{ route('fixed-assets.show', $asset) }}">{{ $asset->asset_number }}</a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $asset->name }}</div>
                                @if($asset->serial_number)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">S/N: {{ $asset->serial_number }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $asset->category?->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $asset->purchase_date->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                {{ number_format($asset->purchase_cost, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <span class="font-medium {{ $asset->book_value > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-500' }}">
                                    {{ number_format($asset->book_value, 2) }}
                                </span>
                                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5 mt-1">
                                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ 100 - $asset->depreciation_percentage }}%"></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @php
                                    $statusColors = [
                                        'active' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                        'fully_depreciated' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                        'disposed' => 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100',
                                        'sold' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
                                    ];
                                @endphp
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$asset->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statuses[$asset->status] ?? $asset->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('fixed-assets.show', $asset) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">View</a>
                                @can('edit fixed-assets')
                                <a href="{{ route('fixed-assets.edit', $asset) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No fixed assets found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new fixed asset.</p>
                                    @can('create fixed-assets')
                                    <div class="mt-4">
                                        <a href="{{ route('fixed-assets.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Add Asset
                                        </a>
                                    </div>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($assets->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $assets->links() }}
            </div>
        @endif
    </div>
</div>
