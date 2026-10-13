<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Bills" :description="'What suppliers have billed you, and what you still have to pay. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                @can('view recurrent-bills')<x-table.menu-item :href="route('recurrent-bills.index')">Recurring bills</x-table.menu-item>@endcan
                <x-table.menu-item :href="route('vendor-credits.index')">Supplier credits</x-table.menu-item>
                @can('view reports')<x-table.menu-item :href="route('reports.accounts-payable')">Ageing report</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create bills')
                    <a href="{{ route('bills.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New bill
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:bills.bills-table />
</x-app-layout>
