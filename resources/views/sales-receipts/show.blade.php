<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="'Sales receipt '.$salesReceipt->receipt_number">
            <x-slot name="badge"><x-status-badge status="paid" /></x-slot>
            <x-slot name="description">
                {{ $salesReceipt->customer?->name ?? 'Walk-in customer' }} · {{ $salesReceipt->receipt_date->format('j M Y') }} · Total @money($salesReceipt->total)
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('sales-receipts.pdf', $salesReceipt)">Download PDF</x-table.menu-item>
                @can('delete sales-receipts')
                    <x-table.menu-item danger :post="route('sales-receipts.destroy', $salesReceipt)" method="DELETE" confirm="Delete this sales receipt?">Delete</x-table.menu-item>
                @endcan
            </x-slot>
            <x-slot name="actions">
                @can('edit sales-receipts')
                    <a href="{{ route('sales-receipts.edit', $salesReceipt) }}" class="btn-secondary">Edit</a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Receipt Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Header Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Receipt Information
                                </h3>
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                    Paid
                                </span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Receipt Number</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesReceipt->receipt_number }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Receipt Date</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesReceipt->receipt_date->format('j M Y') }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Payment Method</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ ucfirst(str_replace('_', ' ', $salesReceipt->payment_method)) }}</p>
                                </div>
                                @if($warehouseName = \App\Models\Warehouse::nameIfMany($salesReceipt->warehouse_id))
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Warehouse</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $warehouseName }}</p>
                                </div>
                                @endif
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Reference</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesReceipt->reference ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                                Receipt Items
                            </h3>
                            <x-table caption="Items on this receipt">
                                <x-slot name="head">
                                    <x-table.th>Item</x-table.th>
                                    <x-table.th num>Qty</x-table.th>
                                    <x-table.th num class="hidden sm:table-cell">Price</x-table.th>
                                    <x-table.th num>Total</x-table.th>
                                </x-slot>
                                @foreach ($salesReceipt->items as $item)
                                    <tr>
                                        <td>
                                            {{ $item->item?->name ?? $item->description ?? '—' }}
                                            @if ($item->item && $item->description && $item->description !== $item->item->name)<div class="text-xs tbl-muted">{{ $item->description }}</div>@endif
                                        </td>
                                        <td class="num">{{ \App\Support\Figure::qty($item->quantity) }}</td>
                                        <td class="num hidden sm:table-cell">@fig($item->unit_price, false)</td>
                                        <td class="num font-medium">@fig($item->total, false)</td>
                                    </tr>
                                @endforeach
                            </x-table>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if($salesReceipt->notes)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Notes
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">{{ $salesReceipt->notes }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Customer Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Customer
                            </h3>
                            <div class="space-y-2">
                                @if($salesReceipt->customer)
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesReceipt->customer->name }}</p>
                                    @if($salesReceipt->customer->company_name)
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $salesReceipt->customer->company_name }}</p>
                                    @endif
                                    @if($salesReceipt->customer->email)
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $salesReceipt->customer->email }}</p>
                                    @endif
                                    @if($salesReceipt->customer->phone)
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $salesReceipt->customer->phone }}</p>
                                    @endif
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Walk-in Customer</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                Summary
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($salesReceipt->subtotal, 2) }}</span>
                                </div>
                                @if($salesReceipt->tax_amount > 0)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">Tax</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($salesReceipt->tax_amount, 2) }}</span>
                                    </div>
                                @endif
                                @if($salesReceipt->discount_amount > 0)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">Discount</span>
                                        <span class="font-medium text-red-600 dark:text-red-300">-{{ number_format($salesReceipt->discount_amount, 2) }}</span>
                                    </div>
                                @endif
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-lg font-bold text-brand-600 dark:text-brand-300">{{ number_format($salesReceipt->total, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
