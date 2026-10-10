<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Delivery notes" :description="'Paperwork that goes with the goods. Dispatching marks them delivered on the sales order; it doesn\'t change stock or your accounts.'">
            <x-slot name="actions">
                @can('create invoices')
                    <a href="{{ route('delivery-notes.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New delivery note
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:delivery-notes.delivery-notes-table />
</x-app-layout>
