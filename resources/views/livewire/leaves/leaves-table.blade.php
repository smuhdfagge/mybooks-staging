{{-- Leave requests (tables plan T5). --}}
@php
    $user = auth()->user();
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('j M Y') : '—';
    $canBulk = $user->canAny(['approve leaves', 'delete leaves']);
    $ids = $leaves->pluck('id')->map(fn ($id) => (string) $id)->all();
    $labels = \App\Livewire\Leaves\LeavesTable::LABELS;
    $days = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search employee name or staff number" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Starts" :options="$periods" />
            <x-table.pick model="leaveType" label="Kind" :options="$leaveTypes" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('approve leaves')
                    <x-table.bulk-button action="approve" confirm="Approve the ticked requests that are waiting?">Approve</x-table.bulk-button>
                    <x-table.bulk-button action="reject" confirm="Reject the ticked requests that are waiting?">Reject</x-table.bulk-button>
                @endcan
                @can('delete leaves')<x-table.bulk-button action="delete" danger confirm="Delete the ticked requests? Only requests still waiting are deleted.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$leaves" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($leaves->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No leave requests match these filters" />
                @else
                    <x-table.empty title="No leave requests yet" text="Record annual, sick or other leave for your staff and approve it here.">
                        @can('create leaves')<a href="{{ route('leaves.create') }}" class="btn-new">Record leave</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Leave requests" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every request on this page" />@endif
                    <x-table.th>Employee</x-table.th>
                    <x-table.th>Kind</x-table.th>
                    <x-table.th field="start_date" :sort="[$sortField, $sortDirection]">From</x-table.th>
                    <x-table.th>To</x-table.th>
                    <x-table.th field="days" :sort="[$sortField, $sortDirection]" num>Days</x-table.th>
                    <x-table.th>Reason</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($leaves as $leave)
                    @php $ticked = in_array((string) $leave->id, $selectedItems, true); $name = $leave->employee ? trim($leave->employee->first_name.' '.$leave->employee->last_name) : '—'; @endphp
                    <tr wire:key="lv-{{ $leave->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$leave->id" :label="$name" />@endif
                        <td class="max-w-[16rem]">
                            <a href="{{ route('leaves.show', $leave) }}" class="tbl-link block truncate">{{ $name }}</a>
                            @if ($leave->employee?->employee_id)<div class="text-xs tbl-muted">{{ $leave->employee->employee_id }}</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $leave->leaveType?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($leave->start_date) }}</td>
                        <td class="tbl-muted">{{ $date($leave->end_date) }}</td>
                        <td class="num">{{ $days($leave->days) }}</td>
                        <td class="max-w-[16rem] truncate {{ $leave->reason ? 'tbl-muted' : 'tbl-zero' }}">{{ $leave->reason ?: '—' }}</td>
                        <td><x-status-badge :status="$leave->status" :label="$labels[$leave->status] ?? null" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$name">
                                <x-table.menu-item :href="route('leaves.show', $leave)">{{ $leave->status === 'pending' && $user->can('approve leaves') ? 'View, approve or reject' : 'View' }}</x-table.menu-item>
                                @if ($leave->status === 'pending')
                                    @can('approve leaves')<x-table.menu-item wire="approveOne({{ $leave->id }})" confirm="Approve this leave?">Approve</x-table.menu-item>@endcan
                                    @can('edit leaves')<x-table.menu-item :href="route('leaves.edit', $leave)">Edit</x-table.menu-item>@endcan
                                    @can('delete leaves')
                                        <x-table.menu-item wire="deleteOne({{ $leave->id }})" confirm="Delete this request?" danger>Delete</x-table.menu-item>
                                    @endcan
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'request' : 'requests' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $days($totals->days) }}</td>
                        <td colspan="3"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Leave requests">
                @foreach ($leaves as $leave)
                    <li wire:key="lv-card-{{ $leave->id }}">
                        <x-table.card :href="route('leaves.show', $leave)" :title="$leave->employee ? trim($leave->employee->first_name.' '.$leave->employee->last_name) : '—'"
                            :amount="$days($leave->days).' '.((float) $leave->days == 1 ? 'day' : 'days')"
                            :meta="($leave->leaveType?->name ?? '').' · '.$date($leave->start_date).' to '.$date($leave->end_date)">
                            <x-slot name="badge"><x-status-badge :status="$leave->status" :label="$labels[$leave->status] ?? null" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$leaves" />
</div>
