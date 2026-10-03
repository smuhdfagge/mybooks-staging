<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Prepaid &amp; deferred schedules</h2>
            @can('create accrual-schedules')
                <a href="{{ route('accrual-schedules.create') }}" class="btn-primary">New schedule</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <div class="p-4 sm:p-6">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Spread an amount paid or received in advance over the months it covers. A year's rent paid in January sits in Prepaid Expenses and moves to Rent a month at a time; a customer's payment for six months of service sits in Deferred Revenue and becomes income a month at a time. Each month is released on its last day.</p>
                    @livewire('accrual-schedules.accrual-schedules-table')
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
