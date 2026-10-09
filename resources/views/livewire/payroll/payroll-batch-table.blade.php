<div class="relative">
    <x-table-loading />
    <!-- Filters -->
    <div class="mb-4 sm:mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="form-label">Search</label>
                <input aria-label="Search batch number" type="text" wire:model.live.debounce.300ms="search" placeholder="Search batch number..."
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" wire:model.live="status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                    <option value="paid">Paid</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label for="perPage" class="form-label">Per Page</label>
                <select id="perPage" wire:model.live="perPage" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <x-sort-header field="batch_number" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Batch #</x-sort-header>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pay Period</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employees</th>
                        <th scope="col" class="hidden lg:table-cell px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Gross</th>
                        <th scope="col" class="hidden lg:table-cell px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Deductions</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Net Pay</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <x-sort-header field="created_at" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Created</x-sort-header>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($batches as $batch)
                        <tr wire:key="batch-{{ $batch->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('payroll-batches.show', $batch) }}" class="text-sm font-medium text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300">
                                    {{ $batch->batch_number }}
                                </a>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $batch->pay_period_start?->format('M d') }} - {{ $batch->pay_period_end?->format('M d, Y') }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900 dark:text-white">
                                {{ $batch->employee_count }}
                            </td>
                            <td class="hidden lg:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                {{ number_format($batch->total_gross, 2) }}
                            </td>
                            <td class="hidden lg:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-red-600 dark:text-red-400 text-right">
                                {{ number_format($batch->total_deductions, 2) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white text-right">
                                {{ number_format($batch->total_net, 2) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $batch->status === 'draft' ? 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100' : '' }}
                                    {{ $batch->status === 'approved' ? 'bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100' : '' }}
                                    {{ $batch->status === 'processing' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : '' }}
                                    {{ $batch->status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' : '' }}
                                    {{ $batch->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : '' }}
                                    {{ $batch->status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' : '' }}">
                                    {{ ucfirst($batch->status) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $batch->created_at?->format('M d, Y') }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('payroll-batches.show', $batch) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300 dark:hover:text-brand-300">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="h-12 w-12 text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">No payroll batches found</p>
                                    <p class="text-gray-500 dark:text-gray-400 mt-1">Click "Generate Payroll" to create a new batch.</p>
                                    <a href="{{ route('payroll.generate-form') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                                        Generate Payroll
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($batches->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $batches->links() }}
            </div>
        @endif
    </div>
</div>
