<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $category->name }}
            </h2>
            <div class="flex space-x-2">
                @can('edit fixed-asset-categories')
                <a href="{{ route('fixed-asset-categories.edit', $category) }}" class="bg-brand-500 hover:bg-brand-700 text-white font-bold py-2 px-4 rounded">
                    Edit Category
                </a>
                @endcan
                @can('delete fixed-asset-categories')
                <form method="POST" action="{{ route('fixed-asset-categories.destroy', $category) }}" class="inline" data-confirm="Are you sure you want to delete this category?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Delete
                    </button>
                </form>
                @endcan
                <a href="{{ route('fixed-asset-categories.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Categories
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Category Details -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Category Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Category Name</label>
                            <p class="text-gray-900 dark:text-gray-100">{{ $category->name }}</p>
                        </div>

                        <div>
                            <label class="form-label">Category Code</label>
                            <p class="text-gray-900 dark:text-gray-100">{{ $category->code ?? 'N/A' }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="form-label">Description</label>
                            <p class="text-gray-900 dark:text-gray-100">{{ $category->description ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Depreciation Settings -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Default Depreciation Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Default Useful Life</label>
                            <p class="text-gray-900 dark:text-gray-100">{{ $category->default_useful_life ?? 'N/A' }} years</p>
                        </div>

                        <div>
                            <label class="form-label">Default Depreciation Method</label>
                            <p class="text-gray-900 dark:text-gray-100">
                                {{ $category->default_depreciation_method ? ucfirst(str_replace('_', ' ', $category->default_depreciation_method)) : 'N/A' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accounting Configuration -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Chart of Accounts</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Asset Account</label>
                            <p class="text-gray-900 dark:text-gray-100">
                                @if($category->assetAccount)
                                    {{ $category->assetAccount->account_code }} - {{ $category->assetAccount->account_name }}
                                @else
                                    Not Set
                                @endif
                            </p>
                        </div>

                        <div>
                            <label class="form-label">Accumulated Depreciation Account</label>
                            <p class="text-gray-900 dark:text-gray-100">
                                @if($category->accumulatedDepreciationAccount)
                                    {{ $category->accumulatedDepreciationAccount->account_code }} - {{ $category->accumulatedDepreciationAccount->account_name }}
                                @else
                                    Not Set
                                @endif
                            </p>
                        </div>

                        <div>
                            <label class="form-label">Depreciation Expense Account</label>
                            <p class="text-gray-900 dark:text-gray-100">
                                @if($category->depreciationExpenseAccount)
                                    {{ $category->depreciationExpenseAccount->account_code }} - {{ $category->depreciationExpenseAccount->account_name }}
                                @else
                                    Not Set
                                @endif
                            </p>
                        </div>

                        <div>
                            <label class="form-label">Gain/Loss on Disposal Account</label>
                            <p class="text-gray-900 dark:text-gray-100">
                                @if($category->gainLossAccount)
                                    {{ $category->gainLossAccount->account_code }} - {{ $category->gainLossAccount->account_name }}
                                @else
                                    Not Set
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assets in this Category -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Assets in this Category</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Asset Number
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Asset Name
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Purchase Cost
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Book Value
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($category->assets as $asset)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $asset->asset_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $asset->name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            @money($asset->purchase_cost)
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">
                                            @money($asset->book_value)
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                {{ $asset->status === 'active' ? 'bg-green-100 text-green-800' : '' }}
                                                {{ $asset->status === 'under_maintenance' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                {{ $asset->status === 'idle' ? 'bg-gray-100 text-gray-800' : '' }}
                                                {{ $asset->status === 'disposed' ? 'bg-red-100 text-red-800' : '' }}">
                                                {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <a href="{{ route('fixed-assets.show', $asset) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300 dark:hover:text-brand-300">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No assets in this category yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
