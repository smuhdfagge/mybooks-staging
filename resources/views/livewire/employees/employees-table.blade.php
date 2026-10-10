{{-- Employees list (tables plan T5). --}}
@php
    $user = auth()->user();
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('j M Y') : '—';
    $canBulk = $user->canAny(['edit employees', 'delete employees']);
    $ids = $employees->pluck('id')->map(fn ($id) => (string) $id)->all();
    $status = ['active' => ['active', 'Working'], 'on-leave' => ['pending', 'On leave'], 'terminated' => ['inactive', 'Left'], 'resigned' => ['inactive', 'Resigned']];
    $type = fn ($t) => $t ? ucfirst(str_replace(['-', '_'], ' ', $t)) : '—';
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name, staff number, email or phone" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.pick model="department" label="Department" :options="$departments" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit employees')<x-table.bulk-button action="activate">Back at work</x-table.bulk-button>@endcan
                @can('delete employees')<x-table.bulk-button action="delete" danger confirm="Delete the ticked employees? Anyone already paid through payroll is kept.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$employees" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($employees->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No employees match these filters" />
                @else
                    <x-table.empty title="No employees yet" text="Add your staff so you can run payroll, track leave and print payslips.">
                        @can('create employees')<a href="{{ route('employees.create') }}" class="btn-new">New employee</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Employees" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every employee on this page" />@endif
                    <x-table.th field="first_name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th field="employee_id" :sort="[$sortField, $sortDirection]">Staff no.</x-table.th>
                    <x-table.th>Department</x-table.th>
                    <x-table.th>Position</x-table.th>
                    <x-table.th>Type</x-table.th>
                    <x-table.th field="hire_date" :sort="[$sortField, $sortDirection]">Started</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($employees as $employee)
                    @php $ticked = in_array((string) $employee->id, $selectedItems, true); $s = $status[$employee->status] ?? ['draft', ucfirst((string) $employee->status)]; @endphp
                    <tr wire:key="em-{{ $employee->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$employee->id" :label="$employee->full_name" />@endif
                        <td class="max-w-[18rem]">
                            <a href="{{ route('employees.show', $employee) }}" class="tbl-link block truncate">{{ $employee->full_name }}</a>
                            @if ($employee->email)<div class="truncate text-xs tbl-muted">{{ $employee->email }}</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $employee->employee_id ?: '—' }}</td>
                        <td class="max-w-[12rem] truncate {{ $employee->department ? '' : 'tbl-zero' }}">{{ $employee->department?->name ?? '—' }}</td>
                        <td class="max-w-[12rem] truncate {{ $employee->designation ? 'tbl-muted' : 'tbl-zero' }}">{{ $employee->designation?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $type($employee->employment_type) }}</td>
                        <td class="tbl-muted">{{ $date($employee->hire_date) }}</td>
                        <td><x-status-badge :status="$s[0]" :label="$s[1]" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$employee->full_name">
                                <x-table.menu-item :href="route('employees.show', $employee)">View</x-table.menu-item>
                                @can('edit employees')<x-table.menu-item :href="route('employees.edit', $employee)">Edit</x-table.menu-item>@endcan
                                @can('create leaves')<x-table.menu-item :href="route('leaves.create', ['employee_id' => $employee->id])">Record leave</x-table.menu-item>@endcan
                                @can('delete employees')
                                    <x-table.menu-item wire="deleteOne({{ $employee->id }})" :confirm="'Delete '.$employee->full_name.'?'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="8">Total of {{ number_format($employees->total()) }} {{ $employees->total() == 1 ? 'employee' : 'employees' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Employees">
                @foreach ($employees as $employee)
                    @php $s = $status[$employee->status] ?? ['draft', ucfirst((string) $employee->status)]; @endphp
                    <li wire:key="em-card-{{ $employee->id }}">
                        <x-table.card :href="route('employees.show', $employee)" :title="$employee->full_name"
                            :meta="collect([$employee->designation?->name, $employee->department?->name])->filter()->implode(' · ')">
                            @if ($employee->status !== 'active')
                                <x-slot name="badge"><x-status-badge :status="$s[0]" :label="$s[1]" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$employees" />
</div>
