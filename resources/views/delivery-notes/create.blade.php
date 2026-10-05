<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New delivery note for {{ $salesOrder->order_number }}</h2>
            <a href="{{ route('sales-orders.show', $salesOrder) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Back to sales order</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <form action="{{ route('delivery-notes.store') }}" method="POST" class="space-y-6">
                @csrf
                <input type="hidden" name="sales_order_id" value="{{ $salesOrder->id }}">

                <x-card>
                    <div class="p-4 sm:p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="delivery_number" class="form-label">Delivery note number</label>
                                <input type="text" id="delivery_number" value="{{ $deliveryNumber }}" disabled class="form-control bg-gray-100 dark:bg-gray-600">
                            </div>
                            <div>
                                <p class="form-label">Customer</p>
                                <p class="py-2 text-gray-900 dark:text-gray-100">{{ $salesOrder->customer->name }}</p>
                            </div>
                            <div><x-field name="delivery_date" label="Delivery date" type="date" required :value="old('delivery_date', date('Y-m-d'))" /></div>
                            <div><x-field name="shipping_method" label="Delivered by (driver, courier)" :value="old('shipping_method')" maxlength="100" /></div>
                            <div><x-field name="tracking_number" label="Vehicle / tracking number" :value="old('tracking_number')" maxlength="100" /></div>
                            <x-warehouse-picker label="Sent from warehouse" />
                            <div class="md:col-span-3"><x-field name="shipping_address" label="Delivery address" type="textarea" rows="2" :value="old('shipping_address', $salesOrder->customer->address)" /></div>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="p-4 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">What is being delivered</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Enter how many of each line go out on this delivery. Leave 0 for lines not in this delivery.</p>
                        @error('lines')<p class="form-error mb-2">{{ $message }}</p>@enderror
                        <table class="min-w-full line-items">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300">
                                    <th class="text-left pb-2">Item</th>
                                    <th class="text-right pb-2">Ordered</th>
                                    <th class="text-right pb-2">Already delivered</th>
                                    <th class="text-right pb-2">Still to deliver</th>
                                    <th class="text-right pb-2 w-36">Deliver now</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salesOrder->items as $i => $line)
                                    @php($left = $outstanding[$line->id] ?? 0)
                                    <tr class="border-b border-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-gray-100">
                                        <td class="py-2 pr-2" data-label="Item" data-cell="main">{{ $line->description }}
                                            <input type="hidden" name="lines[{{ $i }}][sales_order_item_id]" value="{{ $line->id }}">
                                        </td>
                                        <td class="py-2 text-right" data-label="Ordered">{{ (float) $line->quantity }}</td>
                                        <td class="py-2 text-right" data-label="Already delivered">{{ (float) $line->quantity_fulfilled }}</td>
                                        <td class="py-2 text-right" data-label="Still to deliver">{{ $left }}</td>
                                        <td class="py-2 pl-2" data-label="Deliver now">
                                            <input type="number" name="lines[{{ $i }}][quantity]" aria-label="Deliver now: {{ $line->description }}"
                                                value="{{ old("lines.$i.quantity", $left) }}" min="0" max="{{ $left }}" step="0.01" @disabled($left <= 0)
                                                class="form-control text-sm text-right @error("lines.$i.quantity") border-red-500 @enderror">
                                            @error("lines.$i.quantity")<p class="form-error">{{ $message }}</p>@enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>

                <x-card>
                    <div class="p-4 sm:p-6">
                        <x-field name="notes" label="Notes (printed on the delivery note)" type="textarea" rows="2" :value="old('notes')" />
                        <p class="form-help mt-3">Stock is not taken out by the delivery note. It comes out of stock when the invoice is released (waybill), so the same goods are never counted out twice.</p>
                    </div>
                </x-card>

                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('sales-orders.show', $salesOrder) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    <button type="submit" class="btn-primary">Save delivery note</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
