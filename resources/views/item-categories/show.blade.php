<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('item-categories.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ $itemCategory->name }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Item Category Details</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('item-categories.edit', $itemCategory) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 focus:bg-yellow-600 active:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                </a>
            </div>
        </div>
    </x-slot>



    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Category Info -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Category Information</h3>
                    
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $itemCategory->name }}</dd>
                        </div>
                        
                        @if($itemCategory->parent)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Parent Category</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                <a href="{{ route('item-categories.show', $itemCategory->parent) }}" class="text-brand-600 hover:text-brand-800 dark:text-brand-300">
                                    {{ $itemCategory->parent->name }}
                                </a>
                            </dd>
                        </div>
                        @endif
                        
                        @if($itemCategory->description)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $itemCategory->description }}</dd>
                        </div>
                        @endif
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $itemCategory->is_active ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300' }}">
                                    {{ $itemCategory->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $itemCategory->created_at->format('M d, Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Statistics -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Statistics</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-4 bg-brand-50 dark:bg-brand-900/20 rounded-lg">
                            <div class="text-2xl font-bold text-brand-600 dark:text-brand-300">{{ $itemCategory->items->count() }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Items</div>
                        </div>
                        <div class="text-center p-4 bg-accent-50 dark:bg-accent-900/20 rounded-lg">
                            <div class="text-2xl font-bold text-accent-700 dark:text-accent-300">{{ $itemCategory->children->count() }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Subcategories</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subcategories & Items -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Subcategories -->
            @if($itemCategory->children->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Subcategories</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($itemCategory->children as $child)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $child->name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $child->items->count() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $child->is_active ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300' }}">
                                            {{ $child->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('item-categories.show', $child) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300 mr-3">View</a>
                                        <a href="{{ route('item-categories.edit', $child) }}" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400">Edit</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Items -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Items in this Category</h3>
                        <a href="{{ route('items.create') }}" class="text-sm text-brand-600 hover:text-brand-800 dark:text-brand-300">
                            + Add Item
                        </a>
                    </div>
                    
                    @if($itemCategory->items->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SKU</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($itemCategory->items->take(10) as $item)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('items.show', $item) }}" class="text-sm font-medium text-brand-600 hover:text-brand-800 dark:text-brand-300">
                                            {{ $item->name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $item->sku ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ ucfirst($item->type) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                        {{ number_format($item->selling_price ?? 0, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($itemCategory->items->count() > 10)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                        Showing 10 of {{ $itemCategory->items->count() }} items
                    </p>
                    @endif
                    @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No items</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No items have been added to this category yet.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
