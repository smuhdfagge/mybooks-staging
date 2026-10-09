<div class="relative">
    <x-table-loading />
    <!-- Flash Messages -->
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Filters -->
    <div class="mb-4 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <input aria-label="Search categories" wire:model.live.debounce.300ms="search" type="text" 
                placeholder="Search categories..." 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div class="flex items-center gap-4">
            <!-- Bulk Actions -->
            <x-bulk-actions :actions="['delete' => 'Delete']" :selectedCount="count($selectedItems)" />
            <label for="perPage" class="text-sm text-gray-600 dark:text-gray-400">Show:</label>
            <select id="perPage" wire:model.live="perPage" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">
                        <input aria-label="Select all" type="checkbox" wire:model.live="selectAll"
                            class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                    </th>
                    <x-sort-header field="code" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Code</x-sort-header>
                    <x-sort-header field="name" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Name</x-sort-header>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Depreciation Method
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Useful Life
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Assets
                    </th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($categories as $category)
                    <tr wire:key="category-{{ $category->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-4">
                            <input aria-label="Select row" type="checkbox" wire:model.live="selectedItems" value="{{ $category->id }}"
                                class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            {{ $category->code ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            {{ $category->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            {{ $category->default_depreciation_method ? ucwords(str_replace('_', ' ', $category->default_depreciation_method)) : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            {{ $category->default_useful_life ? $category->default_useful_life . ' years' : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200">
                                {{ $category->assets_count }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end gap-3">
                                @can('view fixed-asset-categories')
                                    <a href="{{ route('fixed-asset-categories.show', $category) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300">View</a>
                                @endcan
                                @can('edit fixed-asset-categories')
                                    <a href="{{ route('fixed-asset-categories.edit', $category) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300">Edit</a>
                                @endcan
                                @can('delete fixed-asset-categories')
                                    @if($category->assets_count == 0)
                                        <button wire:click="delete({{ $category->id }})" wire:confirm="Are you sure you want to delete this category?" class="text-red-600 hover:text-red-900 dark:text-red-400">Delete</button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <p>No categories found.</p>
                            @can('create fixed-asset-categories')
                                <a href="{{ route('fixed-asset-categories.create') }}" class="mt-2 inline-flex items-center text-brand-600 hover:text-brand-500 dark:text-brand-300 dark:hover:text-brand-200">
                                    Create your first category
                                </a>
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $categories->links() }}
    </div>
</div>
