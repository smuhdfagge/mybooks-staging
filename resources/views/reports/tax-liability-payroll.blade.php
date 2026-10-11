{{-- PAYE by month (tables plan T6): income tax taken from pay, by month, department and employee. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $name = fn ($e) => $e ? trim($e->first_name.' '.$e->last_name) : 'Employee removed';
    $pct = fn ($v) => number_format((float) $v, 2).'%';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="PAYE and pension by month" :description="'Income tax taken from pay, '.$from.' to '.$to.', from approved and paid payroll. Amounts in ₦.'"
            export="tax-liability-payroll" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                @can('view payroll')<x-table.menu-item :href="route('payroll.liabilities')">Tax and pension to pay</x-table.menu-item>@endcan
                <x-table.menu-item :href="route('reports.employer-contributions', ['start_date' => $startDate, 'end_date' => $endDate])">Employer contributions</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="PAYE and pension by month" :period="$from.' to '.$to">
        @include('reports.partials.payroll-filters', ['action' => route('reports.tax-liability-payroll')])

        <x-report.stats :cols="4">
            <x-report.stat label="Income tax taken" :value="\App\Support\Figure::show($totals['total_tax'])" />
            <x-report.stat label="Gross pay" :value="\App\Support\Figure::show($totals['total_taxable'])" />
            <x-report.stat label="Average rate" :value="$pct($totals['effective_rate'])" hint="Tax as a share of gross pay" />
            <x-report.stat label="Employees" :value="number_format($totals['employee_count'])" />
        </x-report.stats>

        @if ($monthlyBreakdown->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No payroll in this period" text="Approved or paid payroll with a pay date between these dates shows here." /></div>
        @else
            <section class="space-y-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">By month</h3>
                <x-table caption="Income tax by month">
                    <x-slot name="head">
                        <x-table.th>Month</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Employees</x-table.th>
                        <x-table.th num>Gross pay</x-table.th>
                        <x-table.th num>Income tax</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Rate</x-table.th>
                    </x-slot>
                    @foreach ($monthlyBreakdown as $m)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($m['month'].'-01')->format('F Y') }}</td>
                            <td class="num hidden sm:table-cell">{{ $m['employee_count'] }}</td>
                            <td class="num">@fig($m['total_taxable'])</td>
                            <td class="num font-medium">@fig($m['total_tax'])</td>
                            <td class="num hidden sm:table-cell tbl-muted">{{ $pct($m['effective_rate']) }}</td>
                        </tr>
                    @endforeach
                    <x-slot name="foot">
                        <tr><td>Total</td><td class="num hidden sm:table-cell">{{ $totals['employee_count'] }}</td><td class="num">@fig($totals['total_taxable'])</td><td class="num">@fig($totals['total_tax'])</td><td class="num hidden sm:table-cell">{{ $pct($totals['effective_rate']) }}</td></tr>
                    </x-slot>
                </x-table>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="space-y-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">By department</h3>
                    <x-table caption="Income tax by department">
                        <x-slot name="head">
                            <x-table.th>Department</x-table.th>
                            <x-table.th num class="hidden sm:table-cell">Employees</x-table.th>
                            <x-table.th num>Gross pay</x-table.th>
                            <x-table.th num>Income tax</x-table.th>
                        </x-slot>
                        @foreach ($byDepartment as $d)
                            <tr>
                                <td class="rpt-wrap">{{ $d['department_name'] === 'Unassigned' ? 'No department' : $d['department_name'] }}</td>
                                <td class="num hidden sm:table-cell">{{ $d['employee_count'] }}</td>
                                <td class="num">@fig($d['total_taxable'])</td>
                                <td class="num font-medium">@fig($d['total_tax'])</td>
                            </tr>
                        @endforeach
                    </x-table>
                </section>
                <section class="space-y-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">By employee</h3>
                    <x-table caption="Income tax by employee">
                        <x-slot name="head">
                            <x-table.th>Employee</x-table.th>
                            <x-table.th num class="hidden sm:table-cell">Gross pay</x-table.th>
                            <x-table.th num>Income tax</x-table.th>
                            <x-table.th num>Rate</x-table.th>
                        </x-slot>
                        @foreach ($byEmployee as $e)
                            <tr>
                                <td class="rpt-wrap">{{ $name($e['employee']) }}</td>
                                <td class="num hidden sm:table-cell">@fig($e['taxable_income'])</td>
                                <td class="num font-medium">@fig($e['tax_deducted'])</td>
                                <td class="num tbl-muted">{{ $pct($e['effective_rate']) }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                </section>
            </div>
        @endif
    </x-report.sheet>
</x-app-layout>
