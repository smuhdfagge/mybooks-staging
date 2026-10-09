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
            <div class="mt-1 text-2xl font-semibold text-green-700 dark:text-green-400">{{ $activeAssets }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Cost</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalCost, 2) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Book Value</div>
            <div class="mt-1 text-2xl font-semibold text-brand-600 dark:text-brand-300">{{ number_format($totalValue, 2) }}</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="form-label">Search</label>
                    <input aria-label="Search assets" type="text" wire:model.live.debounce.300ms="search" placeholder="Search assets..." 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label for="categoryFilter" class="form-label">Category</label>
                    <select id="categoryFilter" wire:model.live="categoryFilter" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="statusFilter" class="form-label">Status</label>
                    <select id="statusFilter" wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="perPage" class="form-label">Per Page</label>
                    <select id="perPage" wire:model.live="perPage" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <!-- Bulk Actions -->
                <x-bulk-actions :actions="['activate' => 'Activate', 'dispose' => 'Dispose (write off)', 'delete' => 'Delete']" :selectedCount="count($selectedItems)" />
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
                            <input aria-label="Select all" type="checkbox" wire:model.live="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
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
                                <input aria-label="Select row" type="checkbox" wire:model.live="selectedItems" value="{{ $asset->id }}"
                                    class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-brand-600 dark:text-brand-300">
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
                                <span class="font-medium {{ $asset->book_value > 0 ? 'text-green-700 dark:text-green-400' : 'text-gray-500' }}">
                                    {{ number_format($asset->book_value, 2) }}
                                </span>
                                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5 mt-1">
                                    <div class="bg-brand-600 h-1.5 rounded-full" style="width: {{ 100 - $asset->depreciation_percentage }}%"></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @php
                                    $statusColors = [
                                        'active' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                        'fully_depreciated' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                        'disposed' => 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100',
                                        'sold' => 'bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100',
                                    ];
                                @endphp
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$asset->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statuses[$asset->status] ?? $asset->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('fixed-assets.show', $asset) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300 dark:hover:text-brand-300 mr-3">View</a>
                                @can('edit fixed-assets')
                                <a href="{{ route('fixed-assets.edit', $asset) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300 dark:hover:text-brand-300">Edit</a>
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
                                        <a href="{{ route('fixed-assets.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700">
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

    <!-- Bulk write-off (F2) -->
    @if($showDisposeModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="dispose-modal-title" role="dialog" aria-modal="true" x-data x-trap.inert.noscroll="true" @keydown.escape.window="$wire.closeDisposeModal()">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="closeDisposeModal"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="px-4 pt-5 pb-4 sm:p-6 space-y-4">
                    <h3 id="dispose-modal-title" class="text-lg font-medium text-gray-900 dark:text-gray-100">Write off {{ count($selectedItems) }} asset(s)</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Each asset is taken out of the books with no money received: its cost and depreciation are removed and what is left of its value is posted as a loss.
                        <strong>If you sold an asset, dispose of it on its own page instead</strong>, so you can enter what you got for it.
                    </p>
                    <div>
                        <label for="disposalDate" class="form-label">Disposal date <span class="text-red-600 dark:text-red-300">*</span></label>
                        <input type="date" id="disposalDate" wire:model="disposalDate" max="{{ now()->toDateString() }}" class="form-control">
                        @error('disposalDate')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="disposalMethod" class="form-label">What happened to them</label>
                        <select id="disposalMethod" wire:model="disposalMethod" class="form-control">
                            <option value="scrapped">Scrapped</option>
                            <option value="donated">Donated</option>
                            <option value="lost">Lost or stolen</option>
                            <option value="other">Other</option>
                        </select>
                        @error('disposalMethod')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="disposalReason" class="form-label">Reason</label>
                        <textarea id="disposalReason" wire:model="disposalReason" rows="2" class="form-control" placeholder="e.g. Broken beyond repair"></textarea>
                        @error('disposalReason')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Assets already disposed of or sold are skipped.</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button wire:click="disposeSelected" type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:w-auto sm:text-sm">
                        Write off
                    </button>
                    <button wire:click="closeDisposeModal" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
