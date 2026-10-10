<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Chart of accounts" :description="'The accounts your books are kept in, and the balance of each. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                @can('view reports')<x-table.menu-item :href="route('reports.trial-balance')">Trial balance</x-table.menu-item>@endcan
                @can('view journals')<x-table.menu-item :href="route('journals.index')">Journals</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create chart-of-accounts')
                    <a href="{{ route('chart-of-accounts.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New account
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:chart-of-accounts.chart-of-accounts-table />
</x-app-layout>
