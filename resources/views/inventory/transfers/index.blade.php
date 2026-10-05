<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Stock transfers</h2>
            <a href="{{ route('stock-transfers.create') }}" class="btn-primary">New transfer</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <div class="p-4 sm:p-6">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">A transfer moves stock from one warehouse to another at the cost it was bought at. It doesn't change the value of your stock, unless goods are lost on the way.</p>
                    @livewire('stock-transfers.stock-transfers-table')
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
