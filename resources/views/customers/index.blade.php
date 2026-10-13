<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Customers" :description="'People and businesses you sell to, and what each owes you. Amounts in '.\App\Support\Money::symbol().'.'">
            @canany(['export reports', 'view reports'])
                <x-slot name="more">
                    @can('export reports')<x-table.menu-item :href="route('exports.create', ['type' => 'customers'])">Export customers</x-table.menu-item>@endcan
                    @can('view reports')<x-table.menu-item :href="route('reports.accounts-receivable')">Ageing report</x-table.menu-item>@endcan
                </x-slot>
            @endcanany
            <x-slot name="actions">
                @can('create customers')
                    <a href="{{ route('customers.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New customer
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:customers.customers-table />
</x-app-layout>
