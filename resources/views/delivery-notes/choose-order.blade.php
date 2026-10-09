<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New delivery note</h2>
            <a href="{{ route('delivery-notes.index') }}" class="text-sm text-brand-600 dark:text-brand-300 hover:underline">Back to delivery notes</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <div class="p-4 sm:p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">A delivery note is made from a confirmed sales order. Choose the order you are delivering.</p>
                    @if($orders->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No confirmed sales orders have goods waiting to be delivered.
                            @can('view sales-orders')<a href="{{ route('sales-orders.index') }}" class="text-brand-600 dark:text-brand-300 hover:underline">Go to sales orders</a>.@endcan
                        </p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($orders as $order)
                                <li class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $order->order_number }} · {{ $order->customer?->name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $order->order_date->format('M d, Y') }} · @money($order->total) · {{ ucfirst($order->status) }}</p>
                                    </div>
                                    <a href="{{ route('delivery-notes.create', ['sales_order_id' => $order->id]) }}" class="btn-primary">Deliver</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
