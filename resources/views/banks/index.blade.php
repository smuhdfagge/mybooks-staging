<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Banks" :description="'Your bank accounts, tills and cash boxes, with what is in each. Amounts in '.\App\Support\Money::symbol().'.'">
            @if (\App\Http\Middleware\EnsureFeatureEnabled::enabled('bank_feeds'))
                <x-slot name="more">
                    <x-table.menu-item :href="route('bank-feeds.index')">Bank feeds</x-table.menu-item>
                    <x-table.menu-item :href="route('bank-feeds.lines')">Bank lines to review</x-table.menu-item>
                </x-slot>
            @endif
            <x-slot name="actions">
                @can('create banks')
                    <a href="{{ route('banks.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New account
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    @if (\App\Http\Middleware\EnsureFeatureEnabled::enabled('bank_feeds'))
        @include('bank-feeds._banks-summary')
    @endif
    <livewire:banks.banks-table />
</x-app-layout>
