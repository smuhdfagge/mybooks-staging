<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Edit draft transfer {{ $number }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('stock-transfers.update', $transfer) }}">
                @csrf
                @method('PUT')
                @include('inventory.transfers._form')
            </form>
        </div>
    </div>
</x-app-layout>
