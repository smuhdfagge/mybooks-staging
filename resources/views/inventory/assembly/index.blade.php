<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Assembly orders</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('assembly-orders.report') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">Production report</a>
                <a href="{{ route('assembly-orders.create', ['kind' => 'breakdown']) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">Break down</a>
                <a href="{{ route('assembly-orders.create') }}" class="btn-primary">New build</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <div class="p-4 sm:p-6">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">A build makes finished goods from their components, using a <a href="{{ route('bill-of-materials.index') }}" class="text-brand-600 dark:text-brand-300 hover:underline">bill of materials</a>. The finished goods go into stock at what the components cost plus any extra costs like labour. A break-down takes kits or hampers apart again.</p>
                    @livewire('assembly.assembly-orders-table')
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
