<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Edit {{ $bom->label() }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            @if($bom->assemblyOrders()->exists())
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Changes apply to new assembly orders. Orders already made keep what they used.</p>
            @endif
            <form method="POST" action="{{ route('bill-of-materials.update', $bom) }}">
                @csrf
                @method('PUT')
                @include('inventory.bom._form')
            </form>
        </div>
    </div>
</x-app-layout>
