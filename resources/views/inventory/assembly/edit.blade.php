<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Edit draft {{ $number }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            <form method="POST" action="{{ route('assembly-orders.update', $order) }}">
                @csrf
                @method('PUT')
                @include('inventory.assembly._form')
            </form>
        </div>
    </div>
</x-app-layout>
