<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">New stock transfer {{ $number }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            @if($warehouses->count() < 2)
                <x-card>
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <h3 class="mt-2 text-base font-medium text-gray-900 dark:text-gray-100">Add a second warehouse to move stock between them</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A transfer moves goods from one of your warehouses (or shops) to another. You have only one in use at the moment.</p>
                        @can('create items')
                            <a href="{{ route('warehouses.create') }}" class="btn-primary mt-4 inline-flex">Add a warehouse</a>
                        @endcan
                    </div>
                </x-card>
            @else
                <form method="POST" action="{{ route('stock-transfers.store') }}">
                    @csrf
                    @include('inventory.transfers._form')
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
