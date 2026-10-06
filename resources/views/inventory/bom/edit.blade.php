<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit {{ $bom->label() }}</h2>
            <a href="{{ route('bill-of-materials.show', $bom) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            @if($bom->assemblyOrders()->exists())
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Changes apply to new assembly orders. Orders already made keep what they used.</p>
            @endif
            <form method="POST" action="{{ route('bill-of-materials.update', $bom) }}">
                @csrf
                @method('PUT')
                @include('inventory.bom._form')
            </form>
        </div>
    </div>
</x-app-layout>
