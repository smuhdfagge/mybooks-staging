<div class="relative">
    <x-table-loading />
    <!-- Flash Messages -->
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Filters -->
    <div class="mb-4 sm:mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="form-label">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or code..."
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
            <div>
                <label class="form-label">Applies To</label>
                <select wire:model.live="appliesTo"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">All</option>
                    <option value="sales">Sales Only</option>
                    <option value="purchases">Purchases Only</option>
                    <option value="both">Both</option>
                </select>
            </div>
            <div class="flex items-end">
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="showInactive"
                        class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Show Inactive</span>
                </label>
            </div>
            <div class="flex items-end">
                <button wire:click="clearFilters" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                    Clear Filters
                </button>
            </div>
            <!-- Bulk Actions -->
            <x-bulk-actions :actions="['activate' => 'Activate', 'deactivate' => 'Deactivate', 'delete' => 'Delete']" :selectedCount="count($selectedItems)" />
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">
                            <input type="checkbox" wire:model.live="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rate</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Applies To</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($taxRates as $taxRate)
                        <tr wire:key="taxRate-{{ $taxRate->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $taxRate->id }}"
                                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <a href="{{ route('tax-rates.show', $taxRate) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400">
                                            {{ $taxRate->name }}
                                        </a>
                                        @if($taxRate->is_default)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Default
                                            </span>
                                        @endif
                                        @if($taxRate->is_compound)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                Compound
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $taxRate->code ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $taxRate->formatted_rate }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">
                                {{ $taxRate->type }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">
                                @if($taxRate->applies_to == 'both')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Both</span>
                                @elseif($taxRate->applies_to == 'sales')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Sales</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Purchases</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($taxRate->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end items-center space-x-2">
                                    @if(!$taxRate->is_default)
                                        <button wire:click="toggleDefault({{ $taxRate->id }})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="Set as default" aria-label="Set as default">
                                            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                            </svg>
                                        </button>
                                    @endif
                                    <button wire:click="toggleActive({{ $taxRate->id }})" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200" title="{{ $taxRate->is_active ? 'Deactivate' : 'Activate' }}" aria-label="{{ $taxRate->is_active ? 'Deactivate' : 'Activate' }}">
                                        @if($taxRate->is_active)
                                            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        @endif
                                    </button>
                                    <a href="{{ route('tax-rates.edit', $taxRate) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path>
                                </svg>
                                <p class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No tax rates found</p>
                                <p class="mt-2">Get started by creating a new tax rate.</p>
                                <a href="{{ route('tax-rates.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add Tax Rate
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($taxRates->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $taxRates->links() }}
            </div>
        @endif
    </div>
</div>
