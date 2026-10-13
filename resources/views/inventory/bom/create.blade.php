<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="New bill of materials" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            <form method="POST" action="{{ route('bill-of-materials.store') }}">
                @csrf
                @include('inventory.bom._form')
            </form>
        </div>
    </div>
</x-app-layout>
