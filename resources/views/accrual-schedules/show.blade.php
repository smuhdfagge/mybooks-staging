<x-app-layout>
    @php
        $prepaid = $schedule->isPrepaid();
        $dueCount = collect($plan)->where('state', 'due')->count();
        $hasReleases = $schedule->releases->isNotEmpty();
    @endphp
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex flex-wrap items-center gap-3">
                    {{ $schedule->schedule_number }} · {{ $schedule->description }}
                    <x-status-badge :status="$schedule->status" />
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $schedule->typeLabel() }} ·
                    {{ $schedule->months }} {{ \Illuminate\Support\Str::plural('month', $schedule->months) }},
                    {{ $schedule->start_date->format('M Y') }} to {{ $schedule->dueDate($schedule->months)->format('M Y') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('edit accrual-schedules')
                    @if($schedule->isActive())
                        @if($dueCount > 0)
                            <form method="POST" action="{{ route('accrual-schedules.release', $schedule) }}">@csrf
                                <button class="btn-primary">Release due months now</button>
                            </form>
                        @endif
                        @if(! $hasReleases)
                            <a href="{{ route('accrual-schedules.edit', $schedule) }}" class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-xs font-semibold uppercase tracking-widest">Edit</a>
                        @endif
                        <button type="button" x-data @click="$dispatch('open-modal', 'cancel-schedule')" class="inline-flex items-center px-4 py-2 rounded-md border border-red-300 text-red-700 dark:text-red-300 text-xs font-semibold uppercase tracking-widest">Cancel schedule</button>
                    @endif
                @endcan
                @can('delete accrual-schedules')
                    @if(! $hasReleases)
                        <form method="POST" action="{{ route('accrual-schedules.destroy', $schedule) }}" data-confirm="Delete this schedule? Nothing has been released from it, so nothing is posted or undone.">@csrf @method('DELETE')
                            <button class="inline-flex items-center px-4 py-2 rounded-md bg-red-600 text-white text-xs font-semibold uppercase tracking-widest hover:bg-red-700">Delete</button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('accrual-schedules.index') }}" class="inline-flex items-center px-4 py-2 rounded-md bg-gray-600 text-white text-xs font-semibold uppercase tracking-widest">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-error-summary />

            @if($schedule->status === 'cancelled')
                <div class="rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 p-4 text-sm text-amber-800 dark:text-amber-200">
                    Cancelled{{ $schedule->cancelled_at ? ' on '.$schedule->cancelled_at->format('j M Y') : '' }}. The months already released stay posted.
                    @if($schedule->remaining() > 0)
                        @money($schedule->remaining()) is left in {{ $schedule->balanceAccount->name }}; move it with a manual journal if it should go elsewhere.
                    @endif
                </div>
            @elseif($schedule->isActive() && $dueCount > 0)
                <div class="rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 p-4 text-sm text-amber-800 dark:text-amber-200">
                    {{ $dueCount }} {{ \Illuminate\Support\Str::plural('month', $dueCount) }} {{ $dueCount === 1 ? 'is' : 'are' }} due and will be released at the next daily run, or use "Release due months now".
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($schedule->total_amount)</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $prepaid ? 'Expensed so far' : 'Earned so far' }}</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($schedule->released_amount)</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Left in {{ $schedule->balanceAccount->name }}</p>
                    <p class="text-2xl font-semibold text-indigo-600 dark:text-indigo-400">@money($schedule->remaining())</p>
                </x-card>
            </div>

            <x-card title="Month by month">
                <div class="p-4 sm:p-6 pt-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Each month is posted on its last day: {{ $prepaid ? 'Dr '.$schedule->plAccount->name.', Cr '.$schedule->balanceAccount->name : 'Dr '.$schedule->balanceAccount->name.', Cr '.$schedule->plAccount->name }}.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th class="py-2 pr-3 hidden sm:table-cell">#</th>
                                    <th class="py-2 pr-3">Month</th>
                                    <th class="py-2 pr-3 text-right">Amount</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($plan as $row)
                                    <tr>
                                        <td class="py-2 pr-3 hidden sm:table-cell">{{ $row['sequence'] }}</td>
                                        <td class="py-2 pr-3 whitespace-nowrap">{{ $row['due_date']->format('F Y') }}
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $row['due_date']->format('j M Y') }}</span></td>
                                        <td class="py-2 pr-3 text-right whitespace-nowrap">@money($row['amount'])</td>
                                        <td class="py-2">
                                            @switch($row['state'])
                                                @case('released')
                                                    <span class="text-green-700 dark:text-green-400 font-medium">Released</span>
                                                    @if($row['release']->journal)
                                                        in <a href="{{ route('journals.show', $row['release']->journal) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row['release']->journal->journal_number }}</a>
                                                    @endif
                                                    @if($row['release']->note)
                                                        <span class="block text-xs text-amber-700 dark:text-amber-300">{{ $row['release']->note }}</span>
                                                    @endif
                                                    @break
                                                @case('due')
                                                    <span class="text-amber-700 dark:text-amber-300 font-medium">Due</span>
                                                    @break
                                                @case('stopped')
                                                    <span class="text-gray-500 dark:text-gray-400">Not released (cancelled)</span>
                                                    @break
                                                @default
                                                    <span class="text-gray-500 dark:text-gray-400">Upcoming</span>
                                            @endswitch
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-card>

            <x-card title="Details">
                <dl class="p-4 sm:p-6 pt-3 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ $prepaid ? 'Expense account' : 'Income account' }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $schedule->plAccount->account_code }} - {{ $schedule->plAccount->name }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ $prepaid ? 'Prepaid account' : 'Deferred revenue account' }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $schedule->balanceAccount->account_code }} - {{ $schedule->balanceAccount->name }}</dd></div>
                    @if($source)
                        @php([$sourceModel, $numberColumn, $sourceRoute] = \App\Models\AccrualSchedule::SOURCES[$schedule->source_type])
                        <div><dt class="text-gray-500 dark:text-gray-400">{{ ucfirst($schedule->source_type) }}</dt>
                            <dd><a href="{{ route($sourceRoute, $source) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $source->{$numberColumn} }}</a> <span class="text-gray-500 dark:text-gray-400">@money($source->total)</span></dd></div>
                    @endif
                    @if($schedule->reference)
                        <div><dt class="text-gray-500 dark:text-gray-400">Reference</dt><dd class="text-gray-900 dark:text-gray-100">{{ $schedule->reference }}</dd></div>
                    @endif
                    @if($schedule->notes)
                        <div class="sm:col-span-2"><dt class="text-gray-500 dark:text-gray-400">Notes</dt><dd class="text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $schedule->notes }}</dd></div>
                    @endif
                    <div><dt class="text-gray-500 dark:text-gray-400">Set up</dt><dd class="text-gray-900 dark:text-gray-100">{{ $schedule->created_at?->format('j M Y') }}{{ $schedule->createdBy ? ' by '.$schedule->createdBy->name : '' }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>

    @if($schedule->isActive())
        @can('edit accrual-schedules')
            <x-modal name="cancel-schedule" title="Cancel this schedule?" maxWidth="md">
                <form method="POST" action="{{ route('accrual-schedules.cancel', $schedule) }}" class="p-6 space-y-4">
                    @csrf
                    <p class="text-sm text-gray-700 dark:text-gray-300">No more months will be released. The {{ $schedule->releases->count() }} month(s) already released stay posted.</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300"><span class="font-semibold">@money($schedule->remaining())</span> stays in {{ $schedule->balanceAccount->name }}. If it should go elsewhere (for example the rest of the rent was refunded), move it with a manual journal.</p>
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button" x-on:click="$dispatch('close-modal', 'cancel-schedule')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Keep it</button>
                        <button type="submit" class="inline-flex justify-center items-center px-4 py-2 rounded-md bg-red-600 text-white text-xs font-semibold uppercase tracking-widest hover:bg-red-700">Cancel schedule</button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endif
</x-app-layout>
