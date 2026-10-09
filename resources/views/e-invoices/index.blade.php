<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">E-invoices</h2>
            @can('view e-invoices')
                <a href="{{ route('settings.e-invoicing') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">E-invoicing settings</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <x-error-summary />
            @include('e-invoices._setup-notice')
            <x-card>
                <div class="p-4 sm:p-6">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Where each invoice and credit note stands with the Nigeria Revenue Service (NRS). Sending a document to NRS does not change your books.</p>
                    @livewire('e-invoices.e-invoices-table')
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
