<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Stock" :description="'What is on hand, item by item, and what it is worth at cost. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                @if (\App\Models\Warehouse::moduleOn())<x-table.menu-item :href="route('warehouses.index')">Warehouses</x-table.menu-item>@endif
                @if (\App\Models\Warehouse::moduleOn() && \App\Http\Middleware\EnsureFeatureEnabled::enabled('stock_transfers'))@can('adjust inventory')<x-table.menu-item :href="route('stock-transfers.index')">Stock transfers</x-table.menu-item>@endcan @endif
                @can('view reports')<x-table.menu-item :href="route('reports.inventory-summary')">Stock report</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @if (\App\Models\Warehouse::moduleOn() && \App\Http\Middleware\EnsureFeatureEnabled::enabled('stock_transfers'))
                @can('adjust inventory')
                    <a href="{{ route('stock-transfers.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        Move stock
                    </a>
                @endcan
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:inventory.inventory-table />
</x-app-layout>
