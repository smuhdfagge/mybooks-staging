<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Sales orders" :description="'Orders customers have placed, before they are invoiced. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="actions">
                @can('create sales-orders')
                    <a href="{{ route('sales-orders.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New sales order
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:sales-orders.sales-orders-table />
</x-app-layout>
