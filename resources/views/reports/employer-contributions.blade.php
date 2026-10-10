{{-- Employer contributions (tables plan T6): what the business pays on top of salaries (pension, NSITF and the like). Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $name = fn ($e) => $e ? trim($e->first_name.' '.$e->last_name) : 'Employee removed';
    $types = $contributionTypes->pluck('name');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Employer contributions" :description="'What the business pays on top of salaries, '.$from.' to '.$to.', from approved and paid payroll. Amounts in ₦.'"
            export="employer-contributions" :filters="['start_date' => $startDate, 'end_date' => $endDate]">
            <x-slot name="more">
                @can('view payroll')<x-table.menu-item :href="route('payroll.liabilities')">Tax and pension to pay</x-table.menu-item>@endcan
                <x-table.menu-item :href="route('reports.tax-liability-payroll', ['start_date' => $startDate, 'end_date' => $endDate])">PAYE and pension by month</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Employer contributions" :period="$from.' to '.$to">
        @include('reports.partials.payroll-filters', ['action' => route('reports.employer-contributions')])

        <x-report.stats :cols="4">
            <x-report.stat label="Contributions" :value="\App\Support\Figure::show($totals['total_employer_contributions'])" />
            <x-report.stat label="Gross pay" :value="\App\Support\Figure::show($totals['total_gross'])" />
            <x-report.stat label="Total staff cost" :value="\App\Support\Figure::show($totals['total_cost'])" hint="Gross pay plus contributions" />
            <x-report.stat label="On top of pay" :value="\App\Support\Figure::percent($totals['total_employer_contributions'], $totals['total_gross'], 2)" :hint="$totals['employee_count'].' employees'" />
        </x-report.stats>

        @if ($contributionTypes->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No employer contributions in this period" text="Contributions on approved or paid payroll show here." /></div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                <section class="space-y-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">By kind</h3>
                    <x-table caption="Contributions by kind">
                        <x-slot name="head">
                            <x-table.th>Contribution</x-table.th>
                            <x-table.th num class="hidden sm:table-cell">Payslips</x-table.th>
                            <x-table.th num>Amount</x-table.th>
                            <x-table.th num>Share</x-table.th>
                        </x-slot>
                        @foreach ($contributionTypes as $t)
                            <tr>
                                <td class="rpt-wrap">{{ $t['name'] }}</td>
                                <td class="num hidden sm:table-cell">{{ $t['count'] }}</td>
                                <td class="num font-medium">@fig($t['total'])</td>
                                <td class="num tbl-muted">{{ \App\Support\Figure::percent($t['total'], $totals['total_employer_contributions']) }}</td>
                            </tr>
                        @endforeach
                        <x-slot name="foot">
                            <tr><td>Total</td><td class="hidden sm:table-cell"></td><td class="num">@fig($totals['total_employer_contributions'])</td><td class="num">100%</td></tr>
                        </x-slot>
                    </x-table>
                </section>
                <section class="space-y-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">By month</h3>
                    <x-table caption="Contributions by month">
                        <x-slot name="head">
                            <x-table.th>Month</x-table.th>
                            <x-table.th num class="hidden sm:table-cell">Gross pay</x-table.th>
                            <x-table.th num>Contributions</x-table.th>
                            <x-table.th num>On top of pay</x-table.th>
                        </x-slot>
                        @foreach ($monthlyTrend as $m)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($m['month'].'-01')->format('F Y') }}</td>
                                <td class="num hidden sm:table-cell">@fig($m['total_gross'])</td>
                                <td class="num font-medium">@fig($m['total_contributions'])</td>
                                <td class="num tbl-muted">{{ \App\Support\Figure::percent($m['total_contributions'], $m['total_gross']) }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                </section>
            </div>

            <section class="space-y-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">By employee</h3>
                <x-table caption="Contributions by employee">
                    <x-slot name="head">
                        <x-table.th>Employee</x-table.th>
                        <x-table.th num class="hidden md:table-cell">Gross pay</x-table.th>
                        @foreach ($types as $t)
                            <x-table.th num class="hidden lg:table-cell">{{ $t }}</x-table.th>
                        @endforeach
                        <x-table.th num>Contributions</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">On top of pay</x-table.th>
                    </x-slot>
                    @foreach ($byEmployee as $e)
                        <tr>
                            <td class="rpt-wrap">{{ $name($e['employee']) }}</td>
                            <td class="num hidden md:table-cell">@fig($e['gross_salary'])</td>
                            @foreach ($types as $t)
                                <td class="num hidden lg:table-cell tbl-muted">@fig($e['details'][$t] ?? 0)</td>
                            @endforeach
                            <td class="num font-medium">@fig($e['employer_contributions'])</td>
                            <td class="num hidden sm:table-cell tbl-muted">{{ number_format($e['cost_ratio'], 2) }}%</td>
                        </tr>
                    @endforeach
                </x-table>
            </section>
        @endif
    </x-report.sheet>
</x-app-layout>
