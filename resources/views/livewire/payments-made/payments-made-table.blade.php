<div>
    {{-- Flash Messages --}}
    @if($successMessage)
        <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">{{ $successMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900/50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">{{ $errorMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
            <input wire:model.live.debounce.300ms="search" type="text" id="search" placeholder="Search payments..."
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 sm:text-sm">
        </div>

        <div>
            <label for="vendor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vendor</label>
            <select wire:model.live="vendor" id="vendor"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 sm:text-sm">
                <option value="">All Vendors</option>
                @foreach($vendors as $v)
                    <option value="{{ $v->id }}">{{ $v->company_name ?: $v->contact_name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="paymentMethod" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method</label>
            <select wire:model.live="paymentMethod" id="paymentMethod"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 sm:text-sm">
                <option value="">All Methods</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method }}">{{ ucfirst($method) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="dateFrom" class="block text-sm font-medium text-gray-700 dark:text-gray-300">From Date</label>
            <input wire:model.live="dateFrom" type="date" id="dateFrom"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 sm:text-sm">
        </div>

        <div>
            <label for="dateTo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">To Date</label>
            <input wire:model.live="dateTo" type="date" id="dateTo"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 sm:text-sm">
        </div>
    </div>

    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <button wire:click="clearFilters" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                Clear Filters
            </button>
        </div>

        {{-- Bulk Actions --}}
        <div class="flex items-center gap-2">
            @if(count($selectedItems) > 0)
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ count($selectedItems) }} selected</span>
            @endif
            <select wire:model="bulkAction" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400">
                <option value="">Bulk Actions</option>
                <option value="delete">Delete Selected</option>
            </select>
            <button wire:click="applyBulkAction" 
                wire:confirm="Are you sure you want to perform this action on the selected payments?"
                class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                @if(empty($selectedItems)) disabled @endif>
                Apply
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="w-12 px-6 py-3">
                        <input type="checkbox" wire:model.live="selectAll"
                            class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700 dark:checked:bg-indigo-500">
                    </th>
                    <th wire:click="sortBy('payment_number')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                        Payment #
                        @if($sortField === 'payment_number')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                        Vendor
                    </th>
                    <th wire:click="sortBy('payment_date')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                        Date
                        @if($sortField === 'payment_date')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                        Bill #
                    </th>
                    <th wire:click="sortBy('amount')" class="cursor-pointer px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                        Amount
                        @if($sortField === 'amount')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                        Method
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                        Reference
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4">
                            <input type="checkbox" wire:model.live="selectedItems" value="{{ $payment->id }}"
                                class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700 dark:checked:bg-indigo-500">
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <a href="{{ route('payments-made.show', $payment) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                {{ $payment->payment_number }}
                            </a>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            @if($payment->vendor)
                                <a href="{{ route('vendors.show', $payment->vendor) }}" class="text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $payment->vendor->company_name ?: $payment->vendor->contact_name }}
                                </a>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">-</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            @if($payment->bill)
                                <a href="{{ route('bills.show', $payment->bill) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                    {{ $payment->bill->bill_number }}
                                </a>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">-</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                            ${{ number_format($payment->amount, 2) }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ ucfirst($payment->payment_method ?? '-') }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ $payment->reference ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <a href="{{ route('payments-made.show', $payment) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">View</a>
                                <a href="{{ route('payments-made.edit', $payment) }}" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-300">Edit</a>
                                <form action="{{ route('payments-made.destroy', $payment) }}" method="POST" class="inline"
                                    onsubmit="return confirm('Are you sure you want to delete this payment?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            No payments found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <label for="perPage" class="text-sm text-gray-600 dark:text-gray-400">Show</label>
            <select wire:model.live="perPage" id="perPage"
                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <span class="text-sm text-gray-600 dark:text-gray-400">entries</span>
        </div>
        <div>
            {{ $payments->links() }}
        </div>
    </div>
</div>
