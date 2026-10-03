<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Credit notes</h2>
            @can('create invoices')
                <a href="{{ route('credit-notes.create') }}" class="btn-primary">New credit note</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <div class="p-4 sm:p-6">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">A credit note reduces what a customer owes you: for goods they returned, faulty goods or a price mistake. Once posted, the credit can be applied to their unpaid invoices or paid back to them.</p>
                    @livewire('credit-notes.credit-notes-table')
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
