{{-- Earnings this year (tables plan T6): each employee's pay so far in a year, month by month on request. Amounts in ₦. --}}
@php
    $name = fn ($e) => $e ? trim($e->first_name.' '.$e->last_name) : 'Employee removed';
    $years = collect(range(now()->year, now()->year - 5))->mapWithKeys(fn ($y) => [$y => $y]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Earnings this year" :description="'What each employee has earned in '.$year.', from approved and paid payroll. Amounts in ₦.'"
            export="ytd-earnings" :filters="['year' => $year, 'employee_id' => $employeeId]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.employee-earnings', array_filter(['employee_id' => $employeeId]))">Employee earnings</x-table.menu-item>
                <x-table.menu-item :href="route('reports.tax-liability-payroll', ['start_date' => $year.'-01-01', 'end_date' => $year.'-12-31'])">PAYE and pension by month</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Earnings this year" :period="(string) $year">
        <x-report.filters :action="route('reports.ytd-earnings')">
            <x-report.pick name="year" label="Year" :value="$year" :all="null" :options="$years" />
            <x-report.pick name="employee_id" label="Employee" :value="$employeeId" all="Everyone" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $name($e)])" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Gross pay" :value="\App\Support\Figure::show($grandTotals['gross'])" :hint="$byEmployee->count().' employees'" />
            <x-report.stat label="Income tax" :value="\App\Support\Figure::show($grandTotals['tax'])" />
            <x-report.stat label="Net pay" :value="\App\Support\Figure::show($grandTotals['net'])" />
            <x-report.stat label="Employer pays on top" :value="\App\Support\Figure::show($grandTotals['employer_contributions'])" />
        </x-report.stats>

        @if ($byEmployee->isEmpty())
            <div class="tbl-wrap"><x-table.empty :title="'No payroll in '.$year" text="Approved or paid payroll for the year shows here." /></div>
        @else
            <x-table caption="Earnings this year">
                <x-slot name="head">
                    <x-table.th>Employee</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Months paid</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Basic</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Allowances</x-table.th>
                    <x-table.th num class="hidden xl:table-cell">Overtime</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Gross</x-table.th>
                    <x-table.th num class="hidden md:table-cell">Income tax</x-table.th>
                    <x-table.th num class="hidden md:table-cell">All deductions</x-table.th>
                    <x-table.th num>Net pay</x-table.th>
                </x-slot>
                @foreach ($byEmployee as $r)
                    <tr>
                        <td class="rpt-wrap">
                            @if ($r['employee'])
                                <a href="{{ route('reports.ytd-earnings', ['year' => $year, 'employee_id' => $r['employee']->id]) }}" class="tbl-link">{{ $name($r['employee']) }}</a>
                            @else
                                {{ $name(null) }}
                            @endif
                            <span class="block text-xs tbl-muted">{{ $r['employee']?->department?->name ?? 'No department' }}</span>
                        </td>
                        <td class="num hidden sm:table-cell">{{ $r['pay_periods'] }}</td>
                        <td class="num hidden lg:table-cell">@fig($r['ytd_basic'])</td>
                        <td class="num hidden lg:table-cell">@fig($r['ytd_allowances'])</td>
                        <td class="num hidden xl:table-cell {{ \App\Support\Figure::tone($r['ytd_overtime']) }}">@fig($r['ytd_overtime'])</td>
                        <td class="num hidden sm:table-cell">@fig($r['ytd_gross'])</td>
                        <td class="num hidden md:table-cell">@fig($r['ytd_tax'])</td>
                        <td class="num hidden md:table-cell">@fig($r['ytd_total_deductions'])</td>
                        <td class="num font-medium">@fig($r['ytd_net'])</td>
                    </tr>
                    @if ($employeeId)
                        @foreach ($r['monthly_breakdown'] as $m => $d)
                            <tr>
                                <td class="rpt-in1 tbl-muted">{{ \Carbon\Carbon::parse($m.'-01')->format('F Y') }}</td>
                                <td class="hidden sm:table-cell"></td>
                                <td class="num hidden lg:table-cell tbl-muted">@fig($d['basic_salary'])</td>
                                <td class="num hidden lg:table-cell tbl-muted">@fig($d['allowances'])</td>
                                <td class="num hidden xl:table-cell tbl-muted">@fig($d['overtime'])</td>
                                <td class="num hidden sm:table-cell tbl-muted">@fig($d['gross'])</td>
                                <td class="num hidden md:table-cell tbl-muted">@fig($d['tax'])</td>
                                <td class="num hidden md:table-cell tbl-muted">@fig($d['deductions'])</td>
                                <td class="num tbl-muted">@fig($d['net'])</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="hidden sm:table-cell"></td>
                        <td class="num hidden lg:table-cell">@fig($grandTotals['basic'])</td>
                        <td class="num hidden lg:table-cell">@fig($grandTotals['allowances'])</td>
                        <td class="num hidden xl:table-cell">@fig($grandTotals['overtime'])</td>
                        <td class="num hidden sm:table-cell">@fig($grandTotals['gross'])</td>
                        <td class="num hidden md:table-cell">@fig($grandTotals['tax'])</td>
                        <td class="num hidden md:table-cell">@fig($grandTotals['deductions'])</td>
                        <td class="num">@fig($grandTotals['net'])</td>
                    </tr>
                </x-slot>
            </x-table>
            @unless ($employeeId)
                <p class="text-sm text-gray-600 dark:text-gray-400">Click a name to see that employee month by month.</p>
            @endunless
        @endif
    </x-report.sheet>
</x-app-layout>
