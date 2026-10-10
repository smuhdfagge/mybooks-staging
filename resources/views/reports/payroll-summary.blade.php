{{-- Payroll summary (tables plan T6): pay by employee for approved and paid runs in a period. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $name = fn ($e) => $e ? trim($e->first_name.' '.$e->last_name) : 'Employee removed';
    $byEmployee = $byEmployee->sortByDesc('gross')->values();
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Payroll summary" :description="'Pay by employee, '.$from.' to '.$to.', from approved and paid payroll. Amounts in ₦.'"
            export="payroll-summary" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.payroll-by-department', ['start_date' => $startDate, 'end_date' => $endDate])">Payroll by department</x-table.menu-item>
                <x-table.menu-item :href="route('reports.payroll-register')">Payroll register</x-table.menu-item>
                @can('view payroll')<x-table.menu-item :href="route('payroll.index')">Payroll runs</x-table.menu-item>@endcan
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Payroll summary" :period="$from.' to '.$to">
        @include('reports.partials.payroll-filters', ['action' => route('reports.payroll-summary')])

        <x-report.stats :cols="4">
            <x-report.stat label="Gross pay" :value="\App\Support\Figure::show($totalGross)" />
            <x-report.stat label="Deductions" :value="\App\Support\Figure::show($totalDeductions)" />
            <x-report.stat label="Net pay" :value="\App\Support\Figure::show($totalNet)" />
            <x-report.stat label="Employees paid" :value="number_format($byEmployee->count())" :hint="number_format($payrolls->count()).' payslips'" />
        </x-report.stats>

        @if ($byEmployee->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No payroll in this period" text="Approved or paid payroll with a pay date between these dates shows here." /></div>
        @else
            <x-table caption="Payroll by employee">
                <x-slot name="head">
                    <x-table.th>Employee</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Payslips</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Gross</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Deductions</x-table.th>
                    <x-table.th num>Net pay</x-table.th>
                </x-slot>
                @foreach ($byEmployee as $r)
                    <tr>
                        <td class="rpt-wrap">
                            @if ($r['employee'])
                                <a href="{{ route('employees.show', $r['employee']) }}" class="tbl-link">{{ $name($r['employee']) }}</a>
                                <span class="text-xs tbl-muted">{{ $r['employee']->employee_id }}</span>
                            @else
                                <span class="tbl-muted">{{ $name(null) }}</span>
                            @endif
                        </td>
                        <td class="num hidden sm:table-cell">{{ $r['count'] }}</td>
                        <td class="num hidden sm:table-cell">@fig($r['gross'])</td>
                        <td class="num hidden sm:table-cell">@fig($r['deductions'])</td>
                        <td class="num font-medium">@fig($r['net'])</td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="num hidden sm:table-cell">{{ number_format($payrolls->count()) }}</td>
                        <td class="num hidden sm:table-cell">@fig($totalGross)</td>
                        <td class="num hidden sm:table-cell">@fig($totalDeductions)</td>
                        <td class="num">@fig($totalNet)</td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
