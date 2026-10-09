<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Bank lines to review</h2>
            <a href="{{ route('bank-feeds.index') }}" class="btn-secondary">Bank feeds</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @include('bank-feeds._not-set-up')
            <livewire:banks.bank-feed-lines-table />
        </div>
    </div>
</x-app-layout>
