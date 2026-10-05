<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit {{ $warehouse->name }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('warehouses.update', $warehouse) }}">
                @csrf
                @method('PUT')
                <x-card class="p-4 sm:p-6">
                    @include('inventory.warehouses._form')
                </x-card>
                <div class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <a href="{{ route('warehouses.show', $warehouse) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</a>
                    <button type="submit" class="btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
