{{-- Salary changes (tables plan T6): each version of each salary structure, and one employee's pay month by month. Amounts in ₦. --}}
@php
    $name = fn ($e) => $e ? trim($e->first_name.' '.$e->last_name) : 'Employee removed';
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('j M Y') : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Salary changes" description="Each version of each salary structure, and an employee's pay month by month. Amounts in ₦."
            export="salary-revision-history" :filters="['employee_id' => $employeeId]">
            <x-slot name="more">
                @can('view payroll')<x-table.menu-item :href="route('salary-structures.index')">Salary structures</x-table.menu-item>@endcan
                <x-table.menu-item :href="route('reports.employee-earnings', array_filter(['employee_id' => $employeeId]))">Employee earnings</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Salary changes" :period="$selectedEmployee ? $name($selectedEmployee) : null">
        <x-report.filters :action="route('reports.salary-revision-history')" button="Show">
            <x-report.pick name="employee_id" label="Employee's pay by month" :value="$employeeId" all="Choose an employee" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $name($e)])" />
        </x-report.filters>

        @if ($selectedEmployee)
            <section class="space-y-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $name($selectedEmployee) }}: pay by month</h3>
                @if ($salaryProgression->isEmpty())
                    <div class="tbl-wrap"><x-table.empty :title="'No payroll for '.$name($selectedEmployee)" text="Approved or paid payslips show here." /></div>
                @else
                    @php $prev = null; @endphp
                    <x-table caption="Pay by month">
                        <x-slot name="head">
                            <x-table.th>Month</x-table.th>
                            <x-table.th num class="hidden md:table-cell">Basic</x-table.th>
                            <x-table.th num class="hidden md:table-cell">Allowances</x-table.th>
                            <x-table.th num>Gross</x-table.th>
                            <x-table.th num class="hidden sm:table-cell">Net pay</x-table.th>
                            <x-table.th num>Change in gross</x-table.th>
                        </x-slot>
                        @foreach ($salaryProgression as $row)
                            @php $change = $prev === null ? 0 : $row['gross_salary'] - $prev; $prev = $row['gross_salary']; @endphp
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row['month'].'-01')->format('F Y') }}</td>
                                <td class="num hidden md:table-cell">@fig($row['basic_salary'])</td>
                                <td class="num hidden md:table-cell">@fig($row['allowances'])</td>
                                <td class="num font-medium">@fig($row['gross_salary'])</td>
                                <td class="num hidden sm:table-cell">@fig($row['net_salary'])</td>
                                <td class="num {{ abs($change) < 0.005 ? 'tbl-zero' : ($change > 0 ? 'text-green-800 dark:text-green-300' : 'tbl-late') }}">{{ abs($change) < 0.005 ? '—' : ($change > 0 ? '+' : '-').number_format(abs($change), 2) }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </section>
        @endif

        <section class="space-y-2">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Salary structure versions</h3>
            @if ($versions->isEmpty())
                <div class="tbl-wrap"><x-table.empty title="No changes yet" text="Each time a salary structure is changed, the old version shows here." /></div>
            @else
                <x-table caption="Salary structure versions">
                    <x-slot name="head">
                        <x-table.th>Structure</x-table.th>
                        <x-table.th num>Basic</x-table.th>
                        <x-table.th class="hidden sm:table-cell">In use</x-table.th>
                        <x-table.th class="hidden md:table-cell">Why it changed</x-table.th>
                        <x-table.th class="hidden lg:table-cell">Changed by</x-table.th>
                    </x-slot>
                    @foreach ($versions as $version)
                        <tr>
                            <td class="rpt-wrap">
                                {{ $version->name ?? $version->salaryStructure?->name }} <span class="tbl-muted">· version {{ $version->version }}</span>
                                @if (! empty($version->items))
                                    <span class="block text-xs tbl-muted">
                                        @foreach ($version->items as $item)
                                            {{ $item['name'] ?? 'Item' }} {{ ($item['amount_type'] ?? '') === 'percentage' ? ($item['amount'] ?? 0).'%' : number_format($item['amount'] ?? 0, 2) }}{{ $loop->last ? '' : ';' }}
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td class="num">@fig($version->basic_salary)</td>
                            <td class="hidden sm:table-cell tbl-muted">{{ $date($version->effective_from) ?? '—' }} to {{ $date($version->effective_to) ?? 'now' }}</td>
                            <td class="rpt-wrap hidden md:table-cell {{ $version->change_reason ? '' : 'tbl-zero' }}">{{ $version->change_reason ?? '—' }}</td>
                            <td class="hidden lg:table-cell tbl-muted">{{ $version->changedByUser?->name ?? '—' }}{{ $version->created_at ? ', '.$version->created_at->format('j M Y') : '' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </section>
    </x-report.sheet>
</x-app-layout>
