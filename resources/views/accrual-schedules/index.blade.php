<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Prepayments and Deferred Income</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Amounts paid or received in advance, spread over the months they cover.</p>
            </div>
            @can('create journals')
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('accrual-schedules.create', ['type' => 'prepaid_expense']) }}" class="btn-primary">New prepaid expense</a>
                    <a href="{{ route('accrual-schedules.create', ['type' => 'deferred_revenue']) }}" class="btn-primary">New income in advance</a>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                @if($schedules->isEmpty())
                    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">No schedules yet. Use one when you pay for something that covers several months (like a year's rent), or receive money for work you will do over several months.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Schedule</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">What for</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Months</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Released</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Left</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                @foreach($schedules as $schedule)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 whitespace-nowrap"><a href="{{ route('accrual-schedules.show', $schedule) }}" class="text-indigo-600 dark:text-indigo-400 font-medium">{{ $schedule->schedule_number }}</a>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $schedule->isPrepaid() ? 'Prepaid expense' : 'Income in advance' }}</span></td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $schedule->description }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300 hidden md:table-cell whitespace-nowrap">{{ $schedule->months }} from {{ $schedule->start_date->format('M Y') }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900 dark:text-gray-100">@money($schedule->total_amount)</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900 dark:text-gray-100">@money($schedule->released_amount)</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">@money($schedule->remaining())</td>
                                        <td class="px-4 py-3 capitalize text-gray-700 dark:text-gray-300">{{ $schedule->status === 'cancelled' ? 'Stopped' : $schedule->status }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $schedules->links() }}</div>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
