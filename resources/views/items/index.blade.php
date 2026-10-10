<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Items" :description="'Goods and services you sell or buy, with prices and what is in stock. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('item-categories.index')">Categories</x-table.menu-item>
                @can('view inventory')<x-table.menu-item :href="route('inventory.index')">Stock levels</x-table.menu-item>@endcan
                @can('export reports')<x-table.menu-item :href="route('exports.create', ['type' => 'items'])">Export items</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create items')
                    <a href="{{ route('items.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New item
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:items.items-table />
</x-app-layout>
