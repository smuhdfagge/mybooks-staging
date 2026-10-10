<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Bank lines to review" :description="'What your bank sent us. Match each line to a record in MyBooks, record it, or ignore it. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('bank-feeds.index')">Bank feeds</x-table.menu-item>
                <x-table.menu-item :href="route('banks.index')">Banks</x-table.menu-item>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        @include('bank-feeds._not-set-up')
        <livewire:banks.bank-feed-lines-table />
    </div>
</x-app-layout>
