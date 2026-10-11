<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Purchase Order') }} #{{ $purchaseOrder->order_number }}
            </h2>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('purchase-orders.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to List
                </a>
                @if($purchaseOrder->status === 'draft')
                    @can('edit purchase-orders')
                    <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="inline-flex items-center px-4 py-2 bg-amber-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-800 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                    <form action="{{ route('purchase-orders.confirm', $purchaseOrder) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Confirm
                        </button>
                    </form>
                    @endcan
                @endif
                @if(in_array($purchaseOrder->status, ['confirmed', 'partially_received', 'received']))
                    @can('create bills')
                    <form action="{{ route('purchase-orders.convert-to-bill', $purchaseOrder) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Convert to Bill
                        </button>
                    </form>
                    @endcan
                @endif
                @if(in_array($purchaseOrder->status, ['draft', 'confirmed']))
                    @can('edit purchase-orders')
                    <form action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" method="POST" class="inline" data-confirm="Are you sure you want to cancel this purchase order?">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Cancel Order
                        </button>
                    </form>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Order Info -->
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
                                    @if($purchaseOrder->status === 'draft') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                    @elseif($purchaseOrder->status === 'confirmed') bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300
                                    @elseif($purchaseOrder->status === 'partially_received') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                    @elseif($purchaseOrder->status === 'received') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                    @elseif($purchaseOrder->status === 'billed') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                    @elseif($purchaseOrder->status === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300
                                    @endif">
                                    {{ str_replace('_', ' ', ucfirst($purchaseOrder->status)) }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Order Number</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $purchaseOrder->order_number }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Order Date</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $purchaseOrder->order_date->format('j M Y') }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Expected Date</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $purchaseOrder->expected_date?->format('j M Y') ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Reference</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $purchaseOrder->reference ?? 'N/A' }}</p>
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
                                Order Items
                            </h3>
                            @php
                                $showReceived = in_array($purchaseOrder->status, ['partially_received', 'received'], true);
                                $anyDiscount = $purchaseOrder->items->contains(fn ($i) => (float) $i->discount > 0);
                            @endphp
                            <x-table caption="Items on this order">
                                <x-slot name="head">
                                    <x-table.th>Item</x-table.th>
                                    <x-table.th num>Qty</x-table.th>
                                    @if ($showReceived)
                                        <x-table.th num class="hidden md:table-cell">Received</x-table.th>
                                    @endif
                                    <x-table.th num class="hidden sm:table-cell">Price</x-table.th>
                                    @if ($anyDiscount)
                                        <x-table.th num class="hidden lg:table-cell">Discount</x-table.th>
                                    @endif
                                    <x-table.th num class="hidden lg:table-cell">VAT</x-table.th>
                                    <x-table.th num>Total</x-table.th>
                                </x-slot>
                                @foreach ($purchaseOrder->items as $item)
                                    <tr>
                                        <td>
                                            {{ $item->item?->name ?? $item->description ?? '—' }}
                                            @if ($item->item && $item->description && $item->description !== $item->item->name)<div class="text-xs tbl-muted">{{ $item->description }}</div>@endif
                                        </td>
                                        <td class="num">{{ \App\Support\Figure::qty($item->quantity) }}</td>
                                        @if ($showReceived)
                                            <td class="num hidden md:table-cell {{ (float) $item->quantity_received < (float) $item->quantity ? 'text-amber-800 dark:text-amber-300' : '' }}">{{ \App\Support\Figure::qty($item->quantity_received) }}</td>
                                        @endif
                                        <td class="num hidden sm:table-cell">@fig($item->unit_price, false)</td>
                                        @if ($anyDiscount)
                                            <td class="num hidden lg:table-cell {{ \App\Support\Figure::tone($item->discount) }}">@fig($item->discount, false)</td>
                                        @endif
                                        <td class="num hidden lg:table-cell">{{ \App\Support\Figure::rate($item->tax_rate) }}</td>
                                        <td class="num font-medium">@fig($item->total, false)</td>
                                    </tr>
                                @endforeach
                            </x-table>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if($purchaseOrder->notes)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Notes
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">{{ $purchaseOrder->notes }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Terms -->
                    @if($purchaseOrder->terms)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Terms & Conditions
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $purchaseOrder->terms }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Vendor Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                Vendor
                            </h3>
                            <div class="space-y-2">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $purchaseOrder->vendor->name }}</p>
                                @if($purchaseOrder->vendor->company_name)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $purchaseOrder->vendor->company_name }}</p>
                                @endif
                                @if($purchaseOrder->vendor->email)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $purchaseOrder->vendor->email }}</p>
                                @endif
                                @if($purchaseOrder->vendor->phone)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $purchaseOrder->vendor->phone }}</p>
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
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($purchaseOrder->subtotal, 2) }}</span>
                                </div>
                                @if($purchaseOrder->discount_amount > 0)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">Discount</span>
                                        <span class="font-medium text-red-600 dark:text-red-300">-{{ number_format($purchaseOrder->discount_amount, 2) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($purchaseOrder->tax_amount, 2) }}</span>
                                </div>
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-lg font-bold text-brand-600 dark:text-brand-300">{{ number_format($purchaseOrder->total, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Created By -->
                    @if($purchaseOrder->createdBy)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Details</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Created By</span>
                                    <span class="text-gray-900 dark:text-gray-100">{{ $purchaseOrder->createdBy->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Created</span>
                                    <span class="text-gray-900 dark:text-gray-100">{{ $purchaseOrder->created_at->format('j M Y') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Last Updated</span>
                                    <span class="text-gray-900 dark:text-gray-100">{{ $purchaseOrder->updated_at->format('j M Y') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Delete Action -->
                    @if($purchaseOrder->status === 'draft')
                        @can('delete purchase-orders')
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Actions</h3>
                                <form action="{{ route('purchase-orders.destroy', $purchaseOrder) }}" method="POST" data-confirm="Are you sure you want to delete this purchase order?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete Purchase Order
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
