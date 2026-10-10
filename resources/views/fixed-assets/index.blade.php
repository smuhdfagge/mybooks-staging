<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Fixed assets" :description="'Vehicles, generators, computers and other things you keep for years, with what they are worth now. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('fixed-asset-categories.index')">Categories</x-table.menu-item>
                <x-table.menu-item :href="route('fixed-assets.register')">Asset register</x-table.menu-item>
                <x-table.menu-item :href="route('fixed-assets.depreciation-schedule')">Depreciation schedule</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                @can('create fixed-assets')
                    <a href="{{ route('fixed-assets.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New asset
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:fixed-assets.fixed-assets-table />
</x-app-layout>
