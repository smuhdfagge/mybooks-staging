{{-- Bank payment list (tables plan T6): net pay to send to each employee's bank. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $name = fn ($e) => $e ? trim($e->first_name.' '.$e->last_name) : 'Employee removed';
    $methods = ['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque'];
    $method = fn ($m) => $methods[$m] ?? ($m ? ucfirst(str_replace('_', ' ', $m)) : 'Not set');
    $statuses = ['approved' => 'Approved, not yet paid', 'paid' => 'Paid'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Bank payment list" :description="'Net pay to send to each employee, pay dates '.$from.' to '.$to.'. Amounts in ₦.'"
            export="bank-disbursement" :filters="['start_date' => $startDate, 'end_date' => $endDate, 'status' => $status, 'payment_method' => $paymentMethod]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.payroll-register')">Payroll register</x-table.menu-item>
                @can('view payroll')<x-table.menu-item :href="route('payroll.index')">Payroll runs</x-table.menu-item>@endcan
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Bank payment list" :period="$from.' to '.$to">
        @component('reports.partials.payroll-filters', ['action' => route('reports.bank-disbursement'), 'startDate' => $startDate, 'endDate' => $endDate])
            @slot('extra')
                <x-report.pick name="status" label="Status" :value="$status" all="Any status" :options="$statuses" />
                <x-report.pick name="payment_method" label="Paid by" :value="$paymentMethod" all="Any way" :options="$methods" />
            @endslot
        @endcomponent

        <x-report.stats :cols="3">
            <x-report.stat label="Net pay to send" :value="\App\Support\Figure::show($totals['total_net'])" />
            <x-report.stat label="Payslips" :value="number_format($totals['count'])" />
            <x-report.stat label="By method" :value="$byPaymentMethod->count() ? $byPaymentMethod->map(fn ($m) => $method($m['method'] === 'Not Specified' ? null : $m['method']).' '.number_format($m['count']))->join(', ') : '—'" />
        </x-report.stats>

        @if ($payrolls->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="Nothing to pay in this period" text="Approved payroll with a pay date between these dates shows here." /></div>
        @else
            <x-table caption="Bank payment list">
                <x-slot name="head">
                    <x-table.th>Employee</x-table.th>
                    <x-table.th class="hidden md:table-cell">Bank</x-table.th>
                    <x-table.th class="hidden sm:table-cell">Account</x-table.th>
                    <x-table.th class="hidden lg:table-cell">Paid by</x-table.th>
                    <x-table.th class="hidden lg:table-cell">Pay date</x-table.th>
                    <x-table.th num>Net pay</x-table.th>
                    <x-table.th class="hidden sm:table-cell">Status</x-table.th>
                </x-slot>
                @foreach ($payrolls as $p)
                    <tr>
                        <td class="rpt-wrap">
                            {{ $name($p->employee) }}
                            <span class="block text-xs tbl-muted">{{ $p->employee?->employee_id }} · {{ $p->payroll_number }}</span>
                        </td>
                        <td class="hidden md:table-cell {{ $p->employee?->bank_name ? '' : 'tbl-zero' }}">{{ $p->employee?->bank_name ?: '—' }}</td>
                        <td class="hidden sm:table-cell tabular-nums {{ $p->employee?->bank_account_number ? 'tbl-muted' : 'tbl-zero' }}">{{ $p->employee?->bank_account_number ? '••••'.substr($p->employee->bank_account_number, -4) : '—' }}</td>
                        <td class="hidden lg:table-cell tbl-muted">{{ $method($p->payment_method) }}</td>
                        <td class="hidden lg:table-cell tbl-muted">{{ $p->pay_date?->format('j M Y') }}</td>
                        <td class="num font-medium">@fig($p->net_salary)</td>
                        <td class="hidden sm:table-cell"><x-status-badge :status="$p->status" :label="$p->status === 'approved' ? 'To pay' : ucfirst($p->status)" /></td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="hidden md:table-cell"></td>
                        <td class="hidden sm:table-cell"></td>
                        <td class="hidden lg:table-cell"></td>
                        <td class="hidden lg:table-cell"></td>
                        <td class="num">@fig($totals['total_net'])</td>
                        <td class="hidden sm:table-cell"></td>
                    </tr>
                </x-slot>
            </x-table>
            <p class="text-xs text-gray-600 dark:text-gray-400">Only the last four digits of account numbers show here. The bank file from a payroll run has them in full.</p>
        @endif
    </x-report.sheet>
</x-app-layout>
