<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Quotations</h2>
            @can('create invoices')
                <a href="{{ route('quotations.create') }}" class="btn-primary">New quotation</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <div class="p-4 sm:p-6">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">A quotation (estimate) tells a customer what you would charge. It does not go into your accounts. When the customer agrees, convert it to a sales order or an invoice.</p>
                    @livewire('quotations.quotations-table')
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
