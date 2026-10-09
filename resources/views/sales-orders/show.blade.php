<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sales Order') }} #{{ $salesOrder->order_number }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('sales-orders.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to List
                </a>
                @if($salesOrder->status === 'draft')
                    <a href="{{ route('sales-orders.edit', $salesOrder) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                    <form action="{{ route('sales-orders.confirm', $salesOrder) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Confirm
                        </button>
                    </form>
                @endif
                @if(in_array($salesOrder->status, ['confirmed', 'processing', 'invoiced', 'completed'], true) && $salesOrder->hasUninvoicedItems())
                    <form action="{{ route('sales-orders.convert', $salesOrder) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Convert to Invoice
                        </button>
                    </form>
                @endif
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('delivery_notes') && in_array($salesOrder->status, \App\Actions\DeliveryNotes\SaveDeliveryNote::OPEN_ORDER_STATUSES, true) && $salesOrder->hasUnfulfilledItems())
                    @can('create invoices')
                        <a href="{{ route('delivery-notes.create', ['sales_order_id' => $salesOrder->id]) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Deliver (delivery note)
                        </a>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Order Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Header Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Order Information
                                </h3>
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    @if($salesOrder->status === 'draft') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                    @elseif($salesOrder->status === 'confirmed') bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300
                                    @elseif($salesOrder->status === 'processing') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                    @elseif($salesOrder->status === 'invoiced') bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300
                                    @elseif($salesOrder->status === 'completed') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                    @elseif($salesOrder->status === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300
                                    @endif">
                                    {{ ucfirst($salesOrder->status) }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Order Number</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesOrder->order_number }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Order Date</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesOrder->order_date->format('M d, Y') }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Expected Delivery</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesOrder->expected_date?->format('M d, Y') ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Reference</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesOrder->reference ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                                Order Items
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Item</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qty</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Delivered</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoiced</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tax</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($salesOrder->items as $item)
                                            <tr>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $item->item?->name ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $item->description }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right">{{ number_format($item->quantity, 2) }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right">{{ number_format((float) $item->quantity_fulfilled, 2) }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right">{{ number_format((float) $item->quantity_invoiced, 2) }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right">{{ number_format($item->unit_price, 2) }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right">{{ $item->tax_rate }}%</td>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 text-right">{{ number_format($item->total, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if($salesOrder->notes)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Notes
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">{{ $salesOrder->notes }}</p>
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
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salesOrder->customer->name }}</p>
                                @if($salesOrder->customer->company_name)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $salesOrder->customer->company_name }}</p>
                                @endif
                                @if($salesOrder->customer->email)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $salesOrder->customer->email }}</p>
                                @endif
                                @if($salesOrder->customer->phone)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $salesOrder->customer->phone }}</p>
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
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($salesOrder->subtotal, 2) }}</span>
                                </div>
                                @if($salesOrder->discount_amount > 0)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">Discount</span>
                                        <span class="font-medium text-red-600 dark:text-red-400">-{{ number_format($salesOrder->discount_amount, 2) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($salesOrder->tax_amount, 2) }}</span>
                                </div>
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-lg font-bold text-brand-600 dark:text-brand-300">{{ number_format($salesOrder->total, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($salesOrder->invoices->isNotEmpty() || $salesOrder->deliveryNotes->isNotEmpty() || $salesOrder->quotation)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6 text-sm space-y-3">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-200 dark:border-gray-700">Related documents</h3>
                                @if($salesOrder->quotation && \App\Http\Middleware\EnsureFeatureEnabled::enabled('quotations'))
                                    <p>From quotation <a href="{{ route('quotations.show', $salesOrder->quotation) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $salesOrder->quotation->quotation_number }}</a></p>
                                @endif
                                @foreach($salesOrder->invoices as $invoice)
                                    <p class="flex justify-between gap-2"><a href="{{ route('invoices.show', $invoice) }}" class="text-brand-600 dark:text-brand-300 hover:underline">Invoice {{ $invoice->invoice_number }}</a><x-status-badge :status="$invoice->status" /></p>
                                @endforeach
                                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('delivery_notes'))
                                    @foreach($salesOrder->deliveryNotes as $note)
                                        <p class="flex justify-between gap-2"><a href="{{ route('delivery-notes.show', $note) }}" class="text-brand-600 dark:text-brand-300 hover:underline">Delivery note {{ $note->delivery_number }}</a><x-status-badge :status="$note->status" /></p>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Actions -->
                    @if($salesOrder->status === 'draft')
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Actions</h3>
                                <form action="{{ route('sales-orders.destroy', $salesOrder) }}" method="POST" data-confirm="Are you sure you want to delete this sales order?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete Sales Order
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
