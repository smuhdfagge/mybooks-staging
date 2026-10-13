<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Tax rates" description="The rates you charge on sales and pay on purchases, such as VAT.">
            <x-slot name="more">
                <x-table.menu-item :href="route('tax-groups.index')">Tax groups</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                @can('create tax-rates')
                    <a href="{{ route('tax-rates.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New tax rate
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:tax-rates.tax-rates-table />
</x-app-layout>
