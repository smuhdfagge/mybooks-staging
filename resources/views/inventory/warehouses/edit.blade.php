<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Edit {{ $warehouse->name }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('warehouses.update', $warehouse) }}">
                @csrf
                @method('PUT')
                <x-card class="p-4 sm:p-6">
                    @include('inventory.warehouses._form')
                </x-card>
                <div class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <a href="{{ route('warehouses.show', $warehouse) }}" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
