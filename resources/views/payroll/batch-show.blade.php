{{-- A payroll run (tables plan T7): its totals, who is in it, and what to do next. Amounts in ₦. --}}
@php
    $b = $payrollBatch;
    $period = ($b->pay_period_start?->format('j M') ?? '—').' – '.($b->pay_period_end?->format('j M Y') ?? '—');
    $statusLabels = ['draft' => 'Draft', 'approved' => 'Approved', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
    $filtered = request()->filled('search') || request()->filled('status') || request()->filled('department');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="'Payroll run '.$b->batch_number" :description="$period.' · '.$b->employee_count.' '.\Illuminate\Support\Str::plural('employee', $b->employee_count).'. Amounts in ₦.'">
            <x-slot name="badge"><x-status-badge :status="$b->status" :label="ucfirst($b->status)" /></x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('payroll-batches.payslips', $b)">Download all payslips</x-table.menu-item>
                <x-table.menu-item :href="route('payroll.index')">All payroll runs</x-table.menu-item>
                @if ($b->status === 'draft')
                    <x-table.menu-item danger :post="route('payroll-batches.destroy', $b)" method="DELETE" confirm="Delete this payroll run and all its payroll records?">Delete payroll run</x-table.menu-item>
                @endif
            </x-slot>
            <x-slot name="actions">
                @if ($b->status === 'draft')
                    <form action="{{ route('payroll-batches.approve', $b) }}" method="POST" data-confirm="Approve all payroll records in this run?">
                        @csrf
                        <button type="submit" class="btn-new">Approve all</button>
                    </form>
                @elseif (in_array($b->status, ['approved', 'failed'], true))
                    <form action="{{ route('payroll-batches.mark-paid', $b) }}" method="POST" data-confirm="Mark all payroll records as paid?" class="flex items-center gap-2">
                        @csrf
                        @include('payroll._paid-from', ['selected' => $b->bank_id])
                        <button type="submit" class="btn-new">{{ $b->status === 'failed' ? 'Try again' : 'Mark all as paid' }}</button>
                    </form>
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-6">
        @if ($b->status === 'processing')
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100">Being paid in the background. Refresh this page in a minute.</p>
        @elseif ($b->status === 'failed')
            <p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100">Paying this run failed and nothing was paid: {{ $b->failure_reason }}. Fix the problem and use Try again.</p>
        @endif

        <x-report.stats :cols="4">
            <x-report.stat label="Employees" :value="$b->employee_count" />
            <x-report.stat label="Gross pay" :value="\App\Support\Figure::show($b->total_gross)" />
            <x-report.stat label="Deductions" :value="\App\Support\Figure::show($b->total_deductions)" hint="Tax, pension and others" />
            <x-report.stat label="Net pay" :value="\App\Support\Figure::show($b->total_net)" />
        </x-report.stats>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 rounded-lg border border-gray-200 bg-white p-4 text-sm sm:grid-cols-4 dark:border-gray-700 dark:bg-gray-800">
            <div><dt class="text-gray-600 dark:text-gray-400">Pay period</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $b->pay_period_start?->format('j M Y') }} to {{ $b->pay_period_end?->format('j M Y') }}</dd></div>
            <div><dt class="text-gray-600 dark:text-gray-400">Tax rate</dt><dd class="font-medium text-gray-900 dark:text-white">{{ rtrim(rtrim(number_format((float) $b->tax_rate, 2), '0'), '.') }}%</dd></div>
            <div><dt class="text-gray-600 dark:text-gray-400">Made by</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $b->createdBy?->name ?? '—' }}</dd></div>
            @if ($b->approved_at)
                <div><dt class="text-gray-600 dark:text-gray-400">Approved</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $b->approved_at->format('j M Y, H:i') }}<span class="font-normal text-gray-600 dark:text-gray-400"> by {{ $b->approvedBy?->name ?? '—' }}</span></dd></div>
            @endif
            @if ($b->paid_at)
                <div><dt class="text-gray-600 dark:text-gray-400">Paid</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $b->paid_at->format('j M Y, H:i') }}</dd></div>
            @endif
        </dl>

        <section class="space-y-3" aria-labelledby="people-title">
            <h3 id="people-title" class="text-base font-semibold text-gray-900 dark:text-white">Employees in this run</h3>
            <x-table.query-bar :action="route('payroll-batches.show', $b)" placeholder="Search name or employee ID" :filtered="$filtered">
                <x-slot name="filters">
                    <x-table.query-select name="department" label="Department" :options="$departments" />
                    <x-table.query-select name="status" label="Status" :options="$statusLabels" />
                </x-slot>
            </x-table.query-bar>

            @if ($payrolls->isEmpty())
                <div class="tbl-wrap">
                    @if ($filtered)
                        <x-table.empty filtered title="Nobody matches these filters" :clear="route('payroll-batches.show', $b)" />
                    @else
                        <x-table.empty title="No one in this run" text="This payroll run has no payroll records." />
                    @endif
                </div>
            @else
                <x-table caption="Employees in this payroll run">
                    <x-slot name="head">
                        <x-table.th>Employee</x-table.th>
                        <x-table.th class="hidden lg:table-cell">Department</x-table.th>
                        <x-table.th num class="hidden xl:table-cell">Basic</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Gross</x-table.th>
                        <x-table.th num class="hidden md:table-cell">Deductions</x-table.th>
                        <x-table.th num>Net pay</x-table.th>
                        <x-table.th class="hidden sm:table-cell">Status</x-table.th>
                        <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                    </x-slot>
                    @foreach ($payrolls as $payroll)
                        <tr>
                            <td>
                                <a href="{{ route('payroll.show', $payroll) }}" class="tbl-link font-medium">{{ trim(($payroll->employee?->first_name ?? '').' '.($payroll->employee?->last_name ?? '')) ?: '—' }}</a>
                                <div class="text-xs tbl-muted">{{ $payroll->employee?->employee_id ?? '—' }}</div>
                            </td>
                            <td class="hidden lg:table-cell">{{ $payroll->employee?->department?->name ?? '—' }}</td>
                            <td class="num hidden xl:table-cell">@fig($payroll->basic_salary, false)</td>
                            <td class="num hidden sm:table-cell">@fig($payroll->gross_salary, false)</td>
                            <td class="num hidden md:table-cell">@fig($payroll->total_deductions, false)</td>
                            <td class="num font-medium">@fig($payroll->net_salary, false)</td>
                            <td class="hidden sm:table-cell"><x-status-badge :status="$payroll->status" :label="$statusLabels[$payroll->status] ?? ucfirst($payroll->status)" /></td>
                            <td class="tbl-menu">
                                <x-table.dropdown sr-label="Actions for this employee">
                                    <x-table.menu-item :href="route('payroll.show', $payroll)">View</x-table.menu-item>
                                    <x-table.menu-item :href="route('payroll.payslip', $payroll)">Payslip</x-table.menu-item>
                                </x-table.dropdown>
                            </td>
                        </tr>
                    @endforeach
                    <x-slot name="foot">
                        <tr>
                            <th scope="row">Whole run</th>
                            <td class="hidden lg:table-cell"></td>
                            <td class="num hidden xl:table-cell">@fig($b->payrolls()->sum('basic_salary'), false)</td>
                            <td class="num hidden sm:table-cell">@fig($b->total_gross, false)</td>
                            <td class="num hidden md:table-cell">@fig($b->total_deductions, false)</td>
                            <td class="num">@fig($b->total_net, false)</td>
                            <td class="hidden sm:table-cell"></td>
                            <td></td>
                        </tr>
                    </x-slot>
                </x-table>
                <x-table.footer :rows="$payrolls" links />
            @endif
        </section>

        @if ($b->notes)
            <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notes</h3>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $b->notes }}</p>
            </section>
        @endif
    </div>
</x-app-layout>
