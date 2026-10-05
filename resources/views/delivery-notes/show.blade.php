@php
    $btn = 'inline-flex items-center justify-center px-3 py-2 border rounded-md font-semibold text-xs uppercase tracking-widest transition';
    $secondary = $btn.' bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600';
    $status = $deliveryNote->status;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Delivery note {{ $deliveryNote->delivery_number }}</h2>
                <x-status-badge :status="$status" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('delivery-notes.print', $deliveryNote) }}" target="_blank" class="{{ $secondary }}">Print</a>
                <a href="{{ route('delivery-notes.pdf', $deliveryNote) }}" class="{{ $secondary }}">PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <x-card>
                        <div class="p-4 sm:p-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div><p class="text-gray-500 dark:text-gray-400">Delivery date</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $deliveryNote->delivery_date->format('M d, Y') }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Sales order</p>
                                @if($deliveryNote->salesOrder)
                                    @can('view sales-orders')
                                        <a href="{{ route('sales-orders.show', $deliveryNote->salesOrder) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $deliveryNote->salesOrder->order_number }}</a>
                                    @else
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $deliveryNote->salesOrder->order_number }}</p>
                                    @endcan
                                @else <p>—</p> @endif
                            </div>
                            <div><p class="text-gray-500 dark:text-gray-400">Delivered by</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $deliveryNote->shipping_method ?: '—' }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Vehicle / tracking</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $deliveryNote->tracking_number ?: '—' }}</p></div>
                            @if($warehouseName = \App\Models\Warehouse::nameIfMany($deliveryNote->warehouse_id))
                                <div><p class="text-gray-500 dark:text-gray-400">Sent from</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $warehouseName }}</p></div>
                            @endif
                            <div><p class="text-gray-500 dark:text-gray-400">Dispatched</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $deliveryNote->dispatched_at?->format('M d, Y H:i') ?? '—' }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Received by</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $deliveryNote->received_by ?: '—' }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Received</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $deliveryNote->received_at?->format('M d, Y H:i') ?? '—' }}</p></div>
                        </div>
                    </x-card>

                    <x-card>
                        <div class="p-4 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Goods</h3>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead><tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th class="px-3 py-2 text-left">Item</th><th class="px-3 py-2 text-right">Ordered</th><th class="px-3 py-2 text-right">This delivery</th>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                    @foreach($deliveryNote->items as $line)
                                        <tr>
                                            <td class="px-3 py-2">{{ $line->description }}</td>
                                            <td class="px-3 py-2 text-right">{{ (float) $line->quantity_ordered }}</td>
                                            <td class="px-3 py-2 text-right font-medium">{{ (float) $line->quantity_delivered }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="form-help mt-4">Delivery notes don't change stock. Stock comes out when the invoice is released (waybill).</p>
                        </div>
                    </x-card>
                    @if($deliveryNote->shipping_address || $deliveryNote->notes)
                        <x-card>
                            <div class="p-4 sm:p-6 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                                @if($deliveryNote->shipping_address)<div><p class="font-medium text-gray-900 dark:text-gray-100">Delivery address</p><p class="whitespace-pre-line">{{ $deliveryNote->shipping_address }}</p></div>@endif
                                @if($deliveryNote->notes)<div><p class="font-medium text-gray-900 dark:text-gray-100">Notes</p><p class="whitespace-pre-line">{{ $deliveryNote->notes }}</p></div>@endif
                            </div>
                        </x-card>
                    @endif
                </div>

                <div class="space-y-6">
                    <x-card>
                        <div class="p-4 sm:p-6 text-sm space-y-1">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Customer</h3>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $deliveryNote->customer->name }}</p>
                            @if($deliveryNote->customer->phone)<p class="text-gray-600 dark:text-gray-400">{{ $deliveryNote->customer->phone }}</p>@endif
                        </div>
                    </x-card>

                    @can('edit invoices')
                        @if(in_array($status, ['draft', 'dispatched', 'in_transit'], true))
                            <x-card>
                                <div class="p-4 sm:p-6 space-y-3">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Next step</h3>
                                    @if($status === 'draft')
                                        <form method="POST" action="{{ route('delivery-notes.dispatch', $deliveryNote) }}">@csrf
                                            <button type="submit" class="btn-primary w-full">Dispatch (goods leave)</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('delivery-notes.confirm', $deliveryNote) }}" class="space-y-2">@csrf
                                            <x-field name="received_by" label="Received by" required placeholder="Name of the person who signed" />
                                            <button type="submit" class="btn-primary w-full">Confirm delivered</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('delivery-notes.cancel', $deliveryNote) }}" data-confirm="Cancel this delivery note?{{ $status !== 'draft' ? ' Its quantities come off the sales order again.' : '' }}">@csrf
                                        <button type="submit" class="{{ $secondary }} w-full">Cancel delivery note</button>
                                    </form>
                                </div>
                            </x-card>
                        @endif
                    @endcan
                    @can('delete invoices')
                        @if(in_array($status, ['draft', 'cancelled'], true))
                            <form method="POST" action="{{ route('delivery-notes.destroy', $deliveryNote) }}" data-confirm="Delete this delivery note?">@csrf @method('DELETE')
                                <button type="submit" class="{{ $btn }} w-full bg-white dark:bg-gray-800 border-red-300 text-red-700 dark:text-red-400 hover:bg-red-50">Delete</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
