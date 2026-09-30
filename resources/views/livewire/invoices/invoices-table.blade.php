<div class="relative">
    <x-table-loading />
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Filters -->
    <div class="mb-4 sm:mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search invoices..."
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select wire:model.live="status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Range</label>
                <select wire:model.live="dateRange" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Per Page</label>
                <select wire:model.live="perPage" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if(count($selectedItems) > 0)
        <div class="mb-4 bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 rounded-lg p-3 sm:p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <span class="text-sm text-blue-700 dark:text-blue-300">
                    <span class="font-semibold">{{ count($selectedItems) }}</span> invoice(s) selected
                </span>
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <select wire:model="bulkAction" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">Select Action</option>
                        <option value="mark_sent">Mark as Sent</option>
                        <option value="mark_cancelled">Mark as Cancelled</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button wire:click="applyBulkAction" wire:loading.attr="disabled" wire:confirm="Are you sure you want to perform this action on the selected invoices?"
                        class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Apply
                    </button>
                    <button wire:click="$set('selectedItems', [])" 
                        class="inline-flex items-center justify-center px-4 py-2 bg-gray-200 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Clear Selection
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left">
                            <input type="checkbox" wire:model.live="selectAll" 
                                class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                        </th>
                        <x-sort-header field="invoice_number" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Invoice #</x-sort-header>
                        <th scope="col" class="hidden sm:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                        <x-sort-header field="invoice_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="hidden md:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Date</x-sort-header>
                        <th scope="col" class="hidden lg:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                        <x-sort-header field="total" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Amount</x-sort-header>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ in_array($invoice->id, $selectedItems) ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                            <td class="px-4 sm:px-6 py-4">
                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $invoice->id }}"
                                    class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <div class="text-sm font-medium text-blue-600 dark:text-blue-400">{{ $invoice->invoice_number }}</div>
                                <div class="sm:hidden text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $invoice->customer?->name ?? '-' }}</div>
                            </td>
                            <td class="hidden sm:table-cell px-4 sm:px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[150px]">{{ $invoice->customer?->name ?? '-' }}</div>
                            </td>
                            <td class="hidden md:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $invoice->invoice_date?->format('M d, Y') }}</td>
                            <td class="hidden lg:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $invoice->due_date?->format('M d, Y') }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-right">
                                <div class="font-medium text-gray-900 dark:text-white">@money($invoice->total)</div>
                                @if($invoice->balance_due > 0)
                                    <div class="text-xs text-red-600 dark:text-red-400">Due: @money($invoice->balance_due)</div>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : '' }}
                                    {{ $invoice->status === 'sent' ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' : '' }}
                                    {{ $invoice->status === 'draft' ? 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300' : '' }}
                                    {{ $invoice->status === 'unpaid' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : '' }}
                                    {{ $invoice->status === 'overdue' ? 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' : '' }}
                                    {{ $invoice->status === 'cancelled' ? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' : '' }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-1 sm:space-x-2">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="p-1 text-blue-600 hover:text-blue-900 dark:text-blue-400" title="View" aria-label="View">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <a href="{{ route('invoices.print', $invoice) }}" class="p-1 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200" title="Print" aria-label="Print" target="_blank">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    </a>
                                    <a href="{{ route('invoices.edit', $invoice) }}" class="p-1 text-yellow-600 hover:text-yellow-900 dark:text-yellow-400" title="Edit" aria-label="Edit">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    @if($invoice->status === 'draft')
                                    <button wire:click="sendInvoice({{ $invoice->id }})" class="p-1 text-green-600 hover:text-green-900 dark:text-green-400" title="Send" aria-label="Send">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                    </button>
                                    @endif
                                    @if($invoice->status === 'paid' && !$invoice->released_at)
                                    <form action="{{ route('invoices.release', $invoice) }}" method="POST" class="inline" data-confirm="Release this invoice and deduct inventory?">
                                        @csrf
                                        <button type="submit" class="p-1 text-purple-600 hover:text-purple-900 dark:text-purple-400" title="Release & Deduct Inventory" aria-label="Release & Deduct Inventory">
                                            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                                        </button>
                                    </form>
                                    @endif
                                    @if($invoice->released_at)
                                    <a href="{{ route('invoices.waybill', $invoice) }}" class="p-1 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400" title="View Waybill" aria-label="View Waybill" target="_blank">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 sm:px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No invoices found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new invoice.</p>
                                <div class="mt-6">
                                    <a href="{{ route('invoices.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        Create Invoice
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
            <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>
