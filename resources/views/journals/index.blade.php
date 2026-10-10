<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Journals" :description="'Every entry in your books: the ones you make by hand and the ones invoices, bills and payments make. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                @can('edit journals')<x-table.menu-item :href="route('journals.bulk-update')">Post, export or import in bulk</x-table.menu-item>@endcan
                @can('view reports')<x-table.menu-item :href="route('reports.general-ledger')">General ledger</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create journals')
                    <a href="{{ route('journals.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New journal
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:journals.journals-table />
</x-app-layout>
