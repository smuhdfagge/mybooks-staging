<div class="relative">
    <x-table-loading />
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Filters -->
    <div class="mb-4 sm:mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="form-label">Search</label>
                <input aria-label="Search by name" type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name..."
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
            </div>
            <div>
                <label for="amountType" class="form-label">Amount Type</label>
                <select id="amountType" wire:model.live="amountType"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="">All Types</option>
                    <option value="fixed">Fixed</option>
                    <option value="percentage">Percentage</option>
                </select>
            </div>
            <div class="flex items-end">
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="showInactive"
                        class="h-4 w-4 text-brand-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded focus:ring-brand-500 dark:text-brand-300">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Show Inactive</span>
                </label>
            </div>
            <div class="flex items-end">
                <button wire:click="clearFilters" class="text-brand-600 hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-300 text-sm">
                    Clear Filters
                </button>
            </div>
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
                            <input aria-label="Select all" type="checkbox" wire:model.live="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                        </th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                        <th scope="col" class="hidden md:table-cell px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pre-Tax</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deductions as $deduction)
                        <tr wire:key="deduction-{{ $deduction->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-4">
                                <input aria-label="Select row" type="checkbox" wire:model.live="selectedItems" value="{{ $deduction->id }}"
                                    class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 sm:h-10 sm:w-10 flex-shrink-0 rounded-full bg-red-100 dark:bg-red-800 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3 min-w-0">
                                        <a href="{{ route('deductions.show', $deduction) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-brand-600 dark:hover:text-brand-300 truncate">{{ $deduction->name }}</a>
                                        @if($deduction->description)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ Str::limit($deduction->description, 40) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm">
                                @if($deduction->amount_type === 'percentage')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">% of Basic</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Fixed</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                                {{ number_format($deduction->amount, 2) }}{{ $deduction->amount_type === 'percentage' ? '%' : '' }}
                            </td>
                            <td class="hidden md:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-center text-sm">
                                @if($deduction->is_taxable)
                                    <span class="text-green-600 dark:text-green-400">Yes</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">No</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $deduction->is_active ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
                                    {{ $deduction->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-1 sm:space-x-2">
                                    <button wire:click="toggleActive({{ $deduction->id }})" class="p-1 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200" title="{{ $deduction->is_active ? 'Deactivate' : 'Activate' }}" aria-label="{{ $deduction->is_active ? 'Deactivate' : 'Activate' }}">
                                        @if($deduction->is_active)
                                            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        @else
                                            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                    </button>
                                    <a href="{{ route('deductions.edit', $deduction) }}" class="p-1 text-brand-600 hover:text-brand-900 dark:text-brand-300 dark:hover:text-brand-300" title="Edit" aria-label="Edit">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 sm:px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No deductions found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding a new deduction type.</p>
                                <div class="mt-6">
                                    <a href="{{ route('deductions.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand-600 hover:bg-brand-700">
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Add Deduction
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($deductions->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $deductions->links() }}
            </div>
        @endif
    </div>
</div>
