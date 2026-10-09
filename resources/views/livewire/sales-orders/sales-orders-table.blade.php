<div class="relative">
    <x-table-loading />
    <!-- Flash Messages -->
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
        <div>
            <label for="search" class="form-label">Search</label>
            <input type="text" id="search" wire:model.live.debounce.300ms="search" placeholder="Order #, Reference, Customer..."
                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:placeholder-gray-500 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
        </div>

        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" wire:model.live="status"
                class="form-control text-sm">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="confirmed">Confirmed</option>
                <option value="processing">Part delivered</option>
                <option value="invoiced">Invoiced</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div>
            <label for="customer" class="form-label">Customer</label>
            <select id="customer" wire:model.live="customer"
                class="form-control text-sm">
                <option value="">All Customers</option>
                @foreach($customers as $cust)
                    <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="dateFrom" class="form-label">From Date</label>
            <input type="date" id="dateFrom" wire:model.live="dateFrom"
                class="form-control text-sm">
        </div>

        <div>
            <label for="dateTo" class="form-label">To Date</label>
            <input type="date" id="dateTo" wire:model.live="dateTo"
                class="form-control text-sm">
        </div>

        <!-- Bulk Actions -->
        <x-bulk-actions :actions="['confirm' => 'Confirm', 'cancel' => 'Cancel', 'delete' => 'Delete']" :selectedCount="count($selectedItems)" />
    </div>

    @if($search || $status || $customer || $dateFrom || $dateTo)
        <div class="mb-4">
            <button wire:click="clearFilters" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-800 dark:hover:text-brand-300">
                Clear all filters
            </button>
        </div>
    @endif

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">
                        <input aria-label="Select all" type="checkbox" wire:model.live="selectAll"
                            class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order #</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order Date</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Expected</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($orders as $order)
                    <tr wire:key="order-{{ $order->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-4">
                            <input aria-label="Select row" type="checkbox" wire:model.live="selectedItems" value="{{ $order->id }}"
                                class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('sales-orders.show', $order) }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300 font-medium">
                                {{ $order->order_number }}
                            </a>
                            @if($order->reference)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Ref: {{ $order->reference }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $order->customer->name }}</div>
                            @if($order->customer->company_name)
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $order->customer->company_name }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            {{ $order->order_date->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            {{ $order->expected_date?->format('M d, Y') ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 text-right font-medium">
                            @money($order->total)
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                @if($order->status === 'draft') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                @elseif($order->status === 'confirmed') bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300
                                @elseif($order->status === 'processing') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                @elseif($order->status === 'invoiced') bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300
                                @elseif($order->status === 'completed') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                @elseif($order->status === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300
                                @endif">
                                {{ ucfirst($order->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('sales-orders.show', $order) }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300 mr-3">View</a>
                            @if($order->status === 'draft')
                                <a href="{{ route('sales-orders.edit', $order) }}" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-300">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No sales orders found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new sales order.</p>
                            <div class="mt-6">
                                <a href="{{ route('sales-orders.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    New Sales Order
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($orders->hasPages())
        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    @endif
</div>
