<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Assembly orders" :description="'Making finished goods from their parts, or breaking them back down. Finished goods go into stock at what the parts cost. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('bill-of-materials.index')">Bills of materials</x-table.menu-item>
                <x-table.menu-item :href="route('assembly-orders.report')">Production report</x-table.menu-item>
                @can('adjust inventory')<x-table.menu-item :href="route('assembly-orders.create', ['kind' => 'breakdown'])">Break down</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('adjust inventory')
                    <a href="{{ route('assembly-orders.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New build
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:assembly.assembly-orders-table />
</x-app-layout>
