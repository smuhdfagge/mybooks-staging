<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Supplier credits" :description="'Goods sent back to vendors and credit notes they have given you. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="actions">
                @can('create bills')
                    <a href="{{ route('vendor-credits.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New supplier credit
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:vendor-credits.vendor-credits-table />
</x-app-layout>
