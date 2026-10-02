<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $schedule->schedule_number }} · {{ $schedule->description }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $schedule->isPrepaid() ? 'Paid in advance' : 'Received in advance' }} ·
                    {{ $schedule->months }} months from {{ $schedule->start_date->format('M Y') }} ·
                    {{ $schedule->status === 'cancelled' ? 'Stopped' : ucfirst($schedule->status) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('create journals')
                    @if($schedule->status === 'active')
                        <form method="POST" action="{{ route('accrual-schedules.release', $schedule) }}">@csrf
                            <button class="btn-primary">Release months due now</button>
                        </form>
                        <form method="POST" action="{{ route('accrual-schedules.cancel', $schedule) }}" data-confirm="Stop this schedule? No more months will be released, and what is left stays in {{ $schedule->balanceAccount->name }}.">@csrf
                            <button class="inline-flex items-center px-4 py-2 rounded-md border border-red-300 text-red-700 dark:text-red-300 text-xs font-semibold uppercase tracking-widest">Stop</button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('accrual-schedules.index') }}" class="inline-flex items-center px-4 py-2 rounded-md bg-gray-600 text-white text-xs font-semibold uppercase tracking-widest">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-card class="p-4"><p class="text-sm text-gray-500 dark:text-gray-400">Total</p><p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($schedule->total_amount)</p></x-card>
                <x-card class="p-4"><p class="text-sm text-gray-500 dark:text-gray-400">{{ $schedule->isPrepaid() ? 'Expensed so far' : 'Earned so far' }}</p><p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($schedule->released_amount)</p></x-card>
                <x-card class="p-4"><p class="text-sm text-gray-500 dark:text-gray-400">Left in {{ $schedule->balanceAccount->name }}</p><p class="text-2xl font-semibold text-indigo-600 dark:text-indigo-400">@money($schedule->remaining())</p></x-card>
            </div>

            <x-card title="Month by month">
                <div class="overflow-x-auto p-6 pt-3">
                    <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-3">Month</th>
                                <th class="py-2 pr-3">Due</th>
                                <th class="py-2 pr-3 text-right">Amount</th>
                                <th class="py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                            @php $released = $schedule->releases->keyBy('sequence'); @endphp
                            @foreach($schedule->monthlyAmounts() as $sequence => $amount)
                                @php $row = $released->get($sequence); @endphp
                                <tr>
                                    <td class="py-2 pr-3">{{ $sequence }} · {{ $schedule->dueDate($sequence)->format('M Y') }}</td>
                                    <td class="py-2 pr-3 whitespace-nowrap">{{ $schedule->dueDate($sequence)->format('d M Y') }}</td>
                                    <td class="py-2 pr-3 text-right whitespace-nowrap">@money($row?->amount ?? $amount)</td>
                                    <td class="py-2">
                                        @if($row)
                                            Released{{ $row->journal ? '' : '' }}
                                            @if($row->journal) in <a href="{{ route('journals.show', $row->journal) }}" class="text-indigo-600 dark:text-indigo-400">{{ $row->journal->journal_number }}</a>@endif
                                            @if($row->note)<span class="block text-xs text-yellow-700 dark:text-yellow-300">{{ $row->note }}</span>@endif
                                        @elseif($schedule->status === 'cancelled')
                                            <span class="text-gray-500 dark:text-gray-400">Stopped</span>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">To come</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="Accounts">
                <dl class="p-6 pt-3 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ $schedule->isPrepaid() ? 'Expense account' : 'Income account' }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $schedule->plAccount->account_code }} {{ $schedule->plAccount->name }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Held in</dt><dd class="text-gray-900 dark:text-gray-100">{{ $schedule->balanceAccount->account_code }} {{ $schedule->balanceAccount->name }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ $schedule->isPrepaid() ? 'Paid' : 'Received' }}</dt><dd class="text-gray-900 dark:text-gray-100">
                        @if($schedule->funding === 'bank') {{ $schedule->recorded_date->format('d M Y') }}, {{ $schedule->fundingAccount?->name }}
                        @elseif($schedule->funding === 'reclassify') moved from {{ $schedule->plAccount->name }} on {{ $schedule->recorded_date->format('d M Y') }}
                        @else already in {{ $schedule->balanceAccount->name }} @endif
                    </dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
