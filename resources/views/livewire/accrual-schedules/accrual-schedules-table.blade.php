{{-- Prepaid and deferred schedules (tables plan T4). --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $label = ['active' => 'Running', 'completed' => 'Finished'];
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, description or reference" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.pick model="type" label="Kind" :options="$types" />
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($schedules->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No schedules match these filters" />
                @else
                    <x-table.empty title="No schedules yet" text="Spread an amount paid or received in advance over the months it covers, such as a year's rent paid in January.">
                        @can('create accrual-schedules')<a href="{{ route('accrual-schedules.create') }}" class="btn-new">New schedule</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Prepaid and deferred schedules" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="schedule_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Description</x-table.th>
                    <x-table.th field="start_date" :sort="[$sortField, $sortDirection]">Months</x-table.th>
                    <x-table.th field="total_amount" :sort="[$sortField, $sortDirection]" num>Total</x-table.th>
                    <x-table.th field="released_amount" :sort="[$sortField, $sortDirection]" num>Moved so far</x-table.th>
                    <x-table.th num>Still to move</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($schedules as $schedule)
                    @php $left = $schedule->status === 'cancelled' ? 0 : $schedule->remaining(); @endphp
                    <tr wire:key="as-{{ $schedule->id }}">
                        <td><a href="{{ route('accrual-schedules.show', $schedule) }}" class="tbl-link">{{ $schedule->schedule_number }}</a></td>
                        <td class="max-w-[22rem]">
                            <span class="block truncate">{{ $schedule->description }}</span>
                            <span class="block truncate text-xs tbl-muted">{{ $schedule->typeLabel() }}{{ $schedule->plAccount ? ' · '.$schedule->plAccount->name : '' }}</span>
                        </td>
                        <td class="tbl-muted">
                            {{ $schedule->start_date->format('M Y') }} to {{ $schedule->dueDate($schedule->months)->format('M Y') }}
                            <span class="block text-xs">{{ $schedule->months }} {{ \Illuminate\Support\Str::plural('month', $schedule->months) }}</span>
                        </td>
                        <td class="num">{{ $money($schedule->total_amount) }}</td>
                        <td class="num {{ (float) $schedule->released_amount > 0 ? '' : 'tbl-zero' }}">{{ (float) $schedule->released_amount > 0 ? $money($schedule->released_amount) : '—' }}</td>
                        <td class="num {{ $left > 0 ? '' : 'tbl-zero' }}">{{ $left > 0 ? $money($left) : '—' }}</td>
                        <td><x-status-badge :status="$schedule->status" :label="$label[$schedule->status] ?? null" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$schedule->schedule_number">
                                <x-table.menu-item :href="route('accrual-schedules.show', $schedule)">View</x-table.menu-item>
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="3">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'schedule' : 'schedules' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td class="num">{{ $money($totals->released) }}</td>
                        <td colspan="3"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Schedules">
                @foreach ($schedules as $schedule)
                    <li wire:key="as-card-{{ $schedule->id }}">
                        <x-table.card :href="route('accrual-schedules.show', $schedule)" :title="$schedule->description" :amount="\App\Support\Money::format($schedule->total_amount)"
                            :meta="$schedule->schedule_number.' · '.$schedule->start_date->format('M Y').', '.$schedule->months.' months'">
                            <x-slot name="badge"><x-status-badge :status="$schedule->status" :label="$label[$schedule->status] ?? null" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$schedules" />
</div>
