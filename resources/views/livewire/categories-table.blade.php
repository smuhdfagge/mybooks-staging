<div class="relative">
    <x-table-loading />
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-brand-50 dark:bg-brand-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Categories</div>
            <div class="text-2xl font-bold text-brand-600 dark:text-brand-300">{{ $categories->count() }}</div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-400">Categories with Assets</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ $categories->filter(fn($cat) => $cat->assets_count > 0)->count() }}
            </div>
        </div>

        <div class="bg-accent-50 dark:bg-accent-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Assets</div>
            <div class="text-2xl font-bold text-accent-700 dark:text-accent-300">
                {{ $categories->sum('assets_count') }}
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="mb-6">
        <input aria-label="Search categories" 
            type="text" 
            wire:model.live.debounce.300ms="search" 
            placeholder="Search categories..."
            class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600"
        >
    </div>

    <!-- Categories Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <x-sort-header field="code" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</x-sort-header>
                    <x-sort-header field="name" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</x-sort-header>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Description
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Assets
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Default Useful Life
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Depreciation Method
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($categories as $category)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $category->code ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $category->name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ Str::limit($category->description, 50) ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-gray-100">
                            <span class="px-2 py-1 bg-brand-100 dark:bg-brand-900 text-brand-800 dark:text-brand-200 rounded-full">
                                {{ $category->assets_count }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-gray-100">
                            {{ $category->default_useful_life ?? 'N/A' }} years
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            {{ $category->default_depreciation_method ? ucfirst(str_replace('_', ' ', $category->default_depreciation_method)) : 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <div class="flex justify-center space-x-2">
                                @can('view fixed-asset-categories')
                                <a href="{{ route('fixed-asset-categories.show', $category) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300 dark:hover:text-brand-300">
                                    View
                                </a>
                                @endcan
                                
                                @can('edit fixed-asset-categories')
                                <a href="{{ route('fixed-asset-categories.edit', $category) }}" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                    Edit
                                </a>
                                @endcan
                                
                                @can('delete fixed-asset-categories')
                                @if($category->assets_count == 0)
                                <button wire:click="delete({{ $category->id }})" wire:confirm="Are you sure you want to delete this category?" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                    Delete
                                </button>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            No categories found.
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
