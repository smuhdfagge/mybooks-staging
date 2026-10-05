@php $breakdown = $order->kind === 'breakdown'; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $breakdown ? 'New break-down' : 'New build' }} <span class="text-gray-500 dark:text-gray-400 font-normal">{{ $number }}</span></h2>
            <a href="{{ route('assembly-orders.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">All assembly orders</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            @if($boms->isEmpty())
                <x-card>
                    <div class="p-8 text-center">
                        <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">First set up a bill of materials</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A bill of materials says what goes into a batch of a finished item, for example 40 bags of feed from 700 kg maize, 250 kg soya and 50 kg premix.</p>
                        @can('create items')
                            <a href="{{ route('bill-of-materials.create') }}" class="btn-primary mt-4 inline-flex">New bill of materials</a>
                        @endcan
                    </div>
                </x-card>
            @else
                <form method="POST" action="{{ route('assembly-orders.store') }}">
                    @csrf
                    @include('inventory.assembly._form')
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
