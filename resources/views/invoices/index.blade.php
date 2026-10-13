<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Invoices" :description="'Money customers owe you and what they have paid. Amounts in '.\App\Support\Money::symbol().'.'">
            @canany(['export reports', 'view reports'])
                <x-slot name="more">
                    @can('export reports')<x-table.menu-item :href="route('exports.create', ['type' => 'invoices'])">Export invoices</x-table.menu-item>@endcan
                    @can('view reports')<x-table.menu-item :href="route('reports.accounts-receivable')">Ageing report</x-table.menu-item>@endcan
                    @can('view reports')<x-table.menu-item :href="route('reports.sales-by-customer')">Sales by customer</x-table.menu-item>@endcan
                </x-slot>
            @endcanany
            <x-slot name="actions">
                @can('create invoices')
                    <a href="{{ route('invoices.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New invoice
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:invoices.invoices-table />
</x-app-layout>
