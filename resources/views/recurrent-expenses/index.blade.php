<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Recurrent expenses" :description="'Expenses MyBooks records for you on a schedule, such as transport or airtime. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="actions">
                @can('create recurrent-expenses')
                    <a href="{{ route('recurrent-expenses.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New recurrent expense
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:recurrent-expenses.recurrent-expenses-table />
</x-app-layout>
