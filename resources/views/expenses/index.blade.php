<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Expenses" :description="'Money spent without a bill: transport, fuel, airtime and the like. Amounts in '.\App\Support\Money::symbol().'.'">
            @can('view recurrent-expenses')
                <x-slot name="more">
                    <x-table.menu-item :href="route('recurrent-expenses.index')">Recurring expenses</x-table.menu-item>
                </x-slot>
            @endcan
            <x-slot name="actions">
                @can('create expenses')
                    <a href="{{ route('expenses.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        Record expense
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:expenses.expenses-table />
</x-app-layout>
