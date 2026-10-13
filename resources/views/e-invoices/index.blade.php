<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="E-invoices" :description="'Where each invoice and credit note stands with the Nigeria Revenue Service (NRS). Sending one to NRS does not change your books. Amounts in '.\App\Support\Money::symbol().'.'">
            @can('view e-invoices')
                <x-slot name="more">
                    <x-table.menu-item :href="route('settings.e-invoicing')">E-invoicing settings</x-table.menu-item>
                </x-slot>
            @endcan
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        <x-error-summary />
        @include('e-invoices._setup-notice')
        <livewire:e-invoices.e-invoices-table />
    </div>
</x-app-layout>
