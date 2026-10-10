{{-- Employee earnings (tables plan T6): each payslip in a period, for everyone or one employee. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $name = fn ($e) => $e ? trim($e->first_name.' '.$e->last_name) : 'Employee removed';
    $who = $employeeId ? $employees->firstWhere('id', (int) $employeeId) : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Employee earnings" :description="'Each payslip '.($who ? 'for '.$name($who).' ' : '').'from '.$from.' to '.$to.', from approved and paid payroll. Amounts in ₦.'"
            export="employee-earnings" :filters="['start_date' => $startDate, 'end_date' => $endDate, 'employee_id' => $employeeId]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.ytd-earnings', array_filter(['employee_id' => $employeeId]))">Earnings this year</x-table.menu-item>
                <x-table.menu-item :href="route('reports.salary-revision-history', array_filter(['employee_id' => $employeeId]))">Salary changes</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Employee earnings" :period="($who ? $name($who).' · ' : '').$from.' to '.$to">
        @component('reports.partials.payroll-filters', ['action' => route('reports.employee-earnings'), 'startDate' => $startDate, 'endDate' => $endDate])
            @slot('extra')
                <x-report.pick name="employee_id" label="Employee" :value="$employeeId" all="Everyone" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $name($e)])" />
            @endslot
        @endcomponent

        <x-report.stats :cols="4">
            <x-report.stat label="Gross pay" :value="\App\Support\Figure::show($totalGross)" :hint="number_format($payrolls->count()).' payslips'" />
            <x-report.stat label="Income tax" :value="\App\Support\Figure::show($totalTax)" />
            <x-report.stat label="All deductions" :value="\App\Support\Figure::show($totalDeductions)" />
            <x-report.stat label="Net pay" :value="\App\Support\Figure::show($totalNet)" />
        </x-report.stats>

        @if ($payrolls->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No payslips in this period" text="Approved or paid payroll with a pay date between these dates shows here." /></div>
        @else
            <x-table caption="Employee earnings">
                <x-slot name="head">
                    <x-table.th>Pay date</x-table.th>
                    <x-table.th>Employee</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Basic</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Allowances</x-table.th>
                    <x-table.th num class="hidden xl:table-cell">Overtime</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Gross</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Income tax</x-table.th>
                    <x-table.th num class="hidden md:table-cell">All deductions</x-table.th>
                    <x-table.th num>Net pay</x-table.th>
                </x-slot>
                @foreach ($payrolls as $p)
                    <tr>
                        <td class="tbl-muted">{{ $p->pay_date->format('j M Y') }}</td>
                        <td class="rpt-wrap">
                            {{ $name($p->employee) }}
                            <span class="block text-xs tbl-muted">{{ $p->employee?->department?->name ?? 'No department' }}</span>
                        </td>
                        <td class="num hidden lg:table-cell">@fig($p->basic_salary)</td>
                        <td class="num hidden lg:table-cell">@fig($p->allowances)</td>
                        <td class="num hidden xl:table-cell {{ \App\Support\Figure::tone($p->overtime_amount) }}">@fig($p->overtime_amount)</td>
                        <td class="num hidden sm:table-cell">@fig($p->gross_salary)</td>
                        <td class="num hidden md:table-cell">@fig($p->tax_deduction)</td>
                        <td class="num hidden md:table-cell">@fig($p->total_deductions)</td>
                        <td class="num font-medium">@fig($p->net_salary)</td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td></td>
                        <td class="num hidden lg:table-cell">@fig($payrolls->sum('basic_salary'))</td>
                        <td class="num hidden lg:table-cell">@fig($totalAllowances)</td>
                        <td class="num hidden xl:table-cell">@fig($totalOvertime)</td>
                        <td class="num hidden sm:table-cell">@fig($totalGross)</td>
                        <td class="num hidden md:table-cell">@fig($totalTax)</td>
                        <td class="num hidden md:table-cell">@fig($totalDeductions)</td>
                        <td class="num">@fig($totalNet)</td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
