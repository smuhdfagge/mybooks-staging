<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Payments made" :description="'Money you have paid suppliers, against bills or in advance. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('supplier-advances.index')">Supplier advances</x-table.menu-item>
                @can('create payments-made')<x-table.menu-item :href="route('supplier-advances.create')">Pay an advance</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create payments-made')
                    <a href="{{ route('payments-made.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        Record payment
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:payments-made.payments-made-table />
</x-app-layout>
