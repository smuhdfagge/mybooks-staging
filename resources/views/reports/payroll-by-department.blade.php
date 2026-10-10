{{-- Payroll by department (tables plan T6): approved and paid payroll in a period, by department. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Payroll by department" :description="'What each department costs in pay, '.$from.' to '.$to.', from approved and paid payroll. Amounts in ₦.'"
            export="payroll-by-department" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.payroll-summary', ['start_date' => $startDate, 'end_date' => $endDate])">Payroll summary</x-table.menu-item>
                @can('view departments')<x-table.menu-item :href="route('departments.index')">Departments</x-table.menu-item>@endcan
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Payroll by department" :period="$from.' to '.$to">
        @include('reports.partials.payroll-filters', ['action' => route('reports.payroll-by-department')])

        <x-report.stats :cols="3">
            <x-report.stat label="Gross pay" :value="\App\Support\Figure::show($totalGross)" />
            <x-report.stat label="Deductions" :value="\App\Support\Figure::show($totalDeductions)" />
            <x-report.stat label="Net pay" :value="\App\Support\Figure::show($totalNet)" />
        </x-report.stats>

        @if ($byDepartment->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No payroll in this period" text="Approved or paid payroll with a pay date between these dates shows here." /></div>
        @else
            <x-table caption="Payroll by department">
                <x-slot name="head">
                    <x-table.th>Department</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Employees</x-table.th>
                    <x-table.th num>Gross</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">of which allowances</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">of which overtime</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Income tax</x-table.th>
                    <x-table.th num class="hidden md:table-cell">All deductions</x-table.th>
                    <x-table.th num>Net pay</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Share</x-table.th>
                </x-slot>
                @foreach ($byDepartment as $r)
                    <tr>
                        <td class="rpt-wrap">
                            @if ($r['department'])
                                <a href="{{ route('departments.show', $r['department']) }}" class="tbl-link">{{ $r['department_name'] }}</a>
                            @else
                                <span class="tbl-muted">No department</span>
                            @endif
                        </td>
                        <td class="num hidden sm:table-cell">{{ $r['employee_count'] }}</td>
                        <td class="num font-medium">@fig($r['gross'])</td>
                        <td class="num hidden lg:table-cell tbl-muted">@fig($r['allowances'])</td>
                        <td class="num hidden lg:table-cell {{ \App\Support\Figure::tone($r['overtime']) ?: 'tbl-muted' }}">@fig($r['overtime'])</td>
                        <td class="num hidden md:table-cell">@fig($r['tax'])</td>
                        <td class="num hidden md:table-cell">@fig($r['deductions'])</td>
                        <td class="num">@fig($r['net'])</td>
                        <td class="num hidden sm:table-cell tbl-muted">{{ \App\Support\Figure::percent($r['gross'], $totalGross) }}</td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="num hidden sm:table-cell">{{ $byDepartment->sum('employee_count') }}</td>
                        <td class="num">@fig($totalGross)</td>
                        <td class="num hidden lg:table-cell">@fig($byDepartment->sum('allowances'))</td>
                        <td class="num hidden lg:table-cell">@fig($byDepartment->sum('overtime'))</td>
                        <td class="num hidden md:table-cell">@fig($byDepartment->sum('tax'))</td>
                        <td class="num hidden md:table-cell">@fig($totalDeductions)</td>
                        <td class="num">@fig($totalNet)</td>
                        <td class="num hidden sm:table-cell">100%</td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
