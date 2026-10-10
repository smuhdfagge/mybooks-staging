{{-- Payroll register (tables plan T6): every payslip for a month with each part of pay. Amounts in ₦. --}}
@php
    $monthText = \Carbon\Carbon::parse($month.'-01')->format('F Y');
    $name = fn ($e) => $e ? trim($e->first_name.' '.$e->last_name) : 'Employee removed';
    $statuses = ['draft' => 'Draft', 'approved' => 'Approved', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Payroll register" :description="'Every payslip for '.$monthText.', with each part of pay. Amounts in ₦.'"
            export="payroll-register" :filters="['month' => $month, 'status' => $status, 'department_id' => $departmentId]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.payroll-summary')">Payroll summary</x-table.menu-item>
                <x-table.menu-item :href="route('reports.bank-disbursement')">Bank payment list</x-table.menu-item>
                @can('view payroll')<x-table.menu-item :href="route('payroll.index')">Payroll runs</x-table.menu-item>@endcan
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Payroll register" :period="$monthText">
        <x-report.filters :action="route('reports.payroll-register')">
            <x-report.date name="month" label="Month" type="month" :value="$month" />
            <x-report.pick name="department_id" label="Department" :value="$departmentId" all="All departments" :options="$departments->pluck('name', 'id')" />
            <x-report.pick name="status" label="Status" :value="$status" all="Any status" :options="$statuses" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Gross pay" :value="\App\Support\Figure::show($totals['gross_salary'])" :hint="$payrolls->unique('employee_id')->count().' employees'" />
            <x-report.stat label="Deductions" :value="\App\Support\Figure::show($totals['total_deductions'])" />
            <x-report.stat label="Net pay" :value="\App\Support\Figure::show($totals['net_salary'])" />
            <x-report.stat label="Payslips" :value="number_format($payrolls->count())"
                :hint="collect($statusCounts)->filter()->map(fn ($n, $s) => $n.' '.strtolower($statuses[$s] ?? $s))->join(', ') ?: null" />
        </x-report.stats>

        @if ($payrolls->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No payslips for this month" text="Run payroll for the month and its payslips show here." /></div>
        @else
            <x-table caption="Payroll register">
                <x-slot name="head">
                    <x-table.th>Employee</x-table.th>
                    <x-table.th class="hidden 2xl:table-cell">Payslip</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Basic</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Allowances</x-table.th>
                    <x-table.th num class="hidden 2xl:table-cell">Overtime</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Gross</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Income tax</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Other deductions</x-table.th>
                    <x-table.th num>Net pay</x-table.th>
                    <x-table.th num class="hidden 2xl:table-cell">Employer pays</x-table.th>
                    <x-table.th class="hidden sm:table-cell">Status</x-table.th>
                </x-slot>
                @foreach ($payrolls as $p)
                    <tr>
                        <td class="rpt-wrap">
                            {{ $name($p->employee) }}
                            <span class="block text-xs tbl-muted">{{ $p->employee?->department?->name ?? 'No department' }}</span>
                        </td>
                        <td class="hidden 2xl:table-cell tbl-muted">{{ $p->payroll_number }}</td>
                        <td class="num hidden lg:table-cell">@fig($p->basic_salary)</td>
                        <td class="num hidden lg:table-cell">@fig($p->allowances)</td>
                        <td class="num hidden 2xl:table-cell {{ \App\Support\Figure::tone($p->overtime_amount) }}">@fig($p->overtime_amount)</td>
                        <td class="num hidden sm:table-cell">@fig($p->gross_salary)</td>
                        <td class="num hidden md:table-cell">@fig($p->tax_deduction)</td>
                        <td class="num hidden md:table-cell">@fig($p->other_deductions)</td>
                        <td class="num font-medium">@fig($p->net_salary)</td>
                        <td class="num hidden 2xl:table-cell tbl-muted">@fig($p->employer_contributions)</td>
                        <td class="hidden sm:table-cell"><x-status-badge :status="$p->status" :label="$statuses[$p->status] ?? ucfirst($p->status)" /></td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="hidden 2xl:table-cell"></td>
                        <td class="num hidden lg:table-cell">@fig($totals['basic_salary'])</td>
                        <td class="num hidden lg:table-cell">@fig($totals['allowances'])</td>
                        <td class="num hidden 2xl:table-cell">@fig($totals['overtime_amount'])</td>
                        <td class="num hidden sm:table-cell">@fig($totals['gross_salary'])</td>
                        <td class="num hidden md:table-cell">@fig($totals['tax_deduction'])</td>
                        <td class="num hidden md:table-cell">@fig($totals['other_deductions'])</td>
                        <td class="num">@fig($totals['net_salary'])</td>
                        <td class="num hidden 2xl:table-cell">@fig($totals['employer_contributions'])</td>
                        <td class="hidden sm:table-cell"></td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
