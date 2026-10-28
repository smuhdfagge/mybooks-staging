<x-app-layout>
    @php
        $prepaid = $schedule->isPrepaid();
        $dueCount = collect($plan)->where('state', 'due')->count();
        $hasReleases = $schedule->releases->isNotEmpty();
    @endphp
    <x-slot name="header">
        <x-table.page-header :title="$schedule->schedule_number.' · '.$schedule->description" :description="$schedule->typeLabel().' · '.$schedule->months.' '.\Illuminate\Support\Str::plural('month', $schedule->months).', '.$schedule->start_date->format('M Y').' to '.$schedule->dueDate($schedule->months)->format('M Y')">
            <x-slot name="badge"><x-status-badge :status="$schedule->status" /></x-slot>
            @canany(['edit accrual-schedules', 'delete accrual-schedules'])
                @if ($schedule->isActive() || ! $hasReleases)
                    <x-slot name="more">
                        @can('edit accrual-schedules')
                            @if ($schedule->isActive())
                                <x-table.menu-item data-open-modal="cancel-schedule">Cancel schedule</x-table.menu-item>
                            @endif
                        @endcan
                        @can('delete accrual-schedules')
                            @if (! $hasReleases)
                                <x-table.menu-item danger :post="route('accrual-schedules.destroy', $schedule)" method="DELETE" confirm="Delete this schedule? Nothing has been released from it, so nothing is posted or undone.">Delete</x-table.menu-item>
                            @endif
                        @endcan
                    </x-slot>
                @endif
            @endcanany
            <x-slot name="actions">
                @can('edit accrual-schedules')
                    @if ($schedule->isActive() && ! $hasReleases)
                        <a href="{{ route('accrual-schedules.edit', $schedule) }}" class="btn-secondary">Edit</a>
                    @endif
                    @if ($schedule->isActive() && $dueCount > 0)
                        <form method="POST" action="{{ route('accrual-schedules.release', $schedule) }}">@csrf
                            <button type="submit" class="btn-new">Release due months now</button>
                        </form>
                    @endif
                @endcan
            </x-slot>
        </x-table.page-header>
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
                    <p class="text-2xl font-semibold text-brand-600 dark:text-brand-300">@money($schedule->remaining())</p>
                </x-card>
            </div>

            <x-card title="Month by month">
                <div class="p-4 sm:p-6 pt-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Each month is posted on its last day: {{ $prepaid ? 'Dr '.$schedule->plAccount->name.', Cr '.$schedule->balanceAccount->name : 'Dr '.$schedule->balanceAccount->name.', Cr '.$schedule->plAccount->name }}.</p>
                    <x-table caption="Month by month">
<x-slot name="head">
                                    <x-table.th class="hidden sm:table-cell">#</x-table.th>
                                    <x-table.th>Month</x-table.th>
                                    <x-table.th num>Amount</x-table.th>
                                    <x-table.th>Status</x-table.th>
</x-slot>

                                @foreach($plan as $row)
                                    <tr>
                                        <td class="hidden sm:table-cell">{{ $row['sequence'] }}</td>
                                        <td>{{ $row['due_date']->format('F Y') }}
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $row['due_date']->format('j M Y') }}</span></td>
                                        <td class="num">@fig($row['amount'], false)</td>
                                        <td>
                                            @switch($row['state'])
                                                @case('released')
                                                    <span class="text-green-700 dark:text-green-400 font-medium">Released</span>
                                                    @if($row['release']->journal)
                                                        in <a href="{{ route('journals.show', $row['release']->journal) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $row['release']->journal->journal_number }}</a>
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
</x-table>
                </div>
            </x-card>

            <x-card title="Details">
                <dl class="p-4 sm:p-6 pt-3 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ $prepaid ? 'Expense account' : 'Income account' }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $schedule->plAccount->account_code }} - {{ $schedule->plAccount->name }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ $prepaid ? 'Prepaid account' : 'Deferred revenue account' }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $schedule->balanceAccount->account_code }} - {{ $schedule->balanceAccount->name }}</dd></div>
                    @if($source)
                        @php([$sourceModel, $numberColumn, $sourceRoute] = \App\Models\AccrualSchedule::SOURCES[$schedule->source_type])
                        <div><dt class="text-gray-500 dark:text-gray-400">{{ ucfirst($schedule->source_type) }}</dt>
                            <dd><a href="{{ route($sourceRoute, $source) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $source->{$numberColumn} }}</a> <span class="text-gray-500 dark:text-gray-400">@money($source->total)</span></dd></div>
                    @endif
                    @if($schedule->setupJournal)
                        {{-- Moved the amount from the document's account into the balance account (MB-53). --}}
                        <div><dt class="text-gray-500 dark:text-gray-400">Moved into {{ $schedule->balanceAccount->name }} by</dt>
                            <dd><a href="{{ route('journals.show', $schedule->setupJournal) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $schedule->setupJournal->journal_number }}</a> <span class="text-gray-500 dark:text-gray-400">{{ $schedule->setupJournal->journal_date?->format('j M Y') }}</span></dd></div>
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
                    @if ($schedule->setup_journal_id)
                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="move_back" value="1" checked class="mt-1">
                            <span>Move what's left (<span class="font-semibold">@money($schedule->remaining())</span>) back to the account the {{ $schedule->source_type }} used, out of {{ $schedule->balanceAccount->name }}.</span>
                        </label>
                    @else
                        <p class="text-sm text-gray-700 dark:text-gray-300"><span class="font-semibold">@money($schedule->remaining())</span> stays in {{ $schedule->balanceAccount->name }}. If it should go elsewhere (for example the rest of the rent was refunded), move it with a manual journal.</p>
                    @endif
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button" x-on:click="$dispatch('close-modal', 'cancel-schedule')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Keep it</button>
                        <button type="submit" class="btn-danger">Cancel schedule</button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endif
</x-app-layout>
