{{-- Tax and pension to pay (tables plan T5). Amounts in ₦. --}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Tax and pension to pay" description="What payroll owes the tax office and the funds, and what you have paid. Amounts in ₦.">
            <x-slot name="more">
                <x-table.menu-item :href="route('payroll.index')">Payroll runs</x-table.menu-item>
                @can('create payroll')<x-table.menu-item :href="route('payroll.tax-templates')">Tax templates</x-table.menu-item>@endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-6">
        {{-- Tax pack 1: each statutory body for the month --}}
        <section class="space-y-3" aria-labelledby="remit-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 id="remit-title" class="text-base font-semibold text-gray-900 dark:text-white">Remittances for {{ $month->format('F Y') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">From approved and paid payroll for the month. Download a schedule to send with each payment.</p>
                </div>
                <form method="GET" action="{{ route('payroll.liabilities') }}" class="flex items-end gap-2">
                    <div><x-field name="month" label="Month" type="month" :value="$month->format('Y-m')" /></div>
                    <button type="submit" class="tbl-chip h-9">Show</button>
                </form>
            </div>
            <x-table caption="Remittances for the month" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Pay to</x-table.th>
                    <x-table.th>Due by</x-table.th>
                    <x-table.th num>From payroll</x-table.th>
                    <x-table.th num>Paid</x-table.th>
                    <x-table.th num>Still to pay</x-table.th>
                    <x-table.th>Schedule</x-table.th>
                </x-slot>
                @foreach ($summary as $row)
                    @php $late = $row->outstanding > 0 && $row->due_date->isPast(); @endphp
                    <tr>
                        <td>
                            {{ $row->label }}
                            @if ($row->body === 'itf')<div class="text-xs tbl-muted">Paid yearly: {{ $row->from->format('M') }} to {{ $row->to->format('M Y') }}</div>@endif
                        </td>
                        <td class="{{ $late ? 'tbl-late' : 'tbl-muted' }}" title="{{ $row->due_rule }}">{{ $row->due_date->format('j M Y') }}@if ($late) · Overdue @endif</td>
                        <td class="num {{ $row->due > 0 ? '' : 'tbl-zero' }}">{{ $money($row->due) }}</td>
                        <td class="num {{ $row->paid > 0 ? '' : 'tbl-zero' }}">{{ $money($row->paid) }}</td>
                        <td class="num {{ $row->outstanding > 0 ? 'font-medium' : 'tbl-zero' }}">{{ $money($row->outstanding) }}</td>
                        <td class="whitespace-nowrap">
                            <a href="{{ route('payroll.liabilities.schedule', ['body' => $row->body, 'month' => $month->format('Y-m'), 'format' => 'pdf']) }}" class="tbl-link">PDF</a>
                            <span class="tbl-zero" aria-hidden="true">·</span>
                            <a href="{{ route('payroll.liabilities.schedule', ['body' => $row->body, 'month' => $month->format('Y-m'), 'format' => 'csv']) }}" class="tbl-link">CSV</a>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="num">{{ $money($summary->sum('due')) }}</td>
                        <td class="num">{{ $money($summary->sum('paid')) }}</td>
                        <td class="num">{{ $money($summary->sum('outstanding')) }}</td>
                        <td></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Remittances for the month">
                @foreach ($summary as $row)
                    @php $late = $row->outstanding > 0 && $row->due_date->isPast(); @endphp
                    <li>
                        <x-table.card :href="route('payroll.liabilities.schedule', ['body' => $row->body, 'month' => $month->format('Y-m'), 'format' => 'pdf'])"
                            :title="$row->label" :amount="$money($row->outstanding)" :meta="'Due '.$row->due_date->format('j M Y').' · '.$money($row->paid).' paid'" :tone="$late ? 'bad' : 'muted'">
                            @if ($late)
                                <x-slot name="alert">Overdue</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs text-gray-600 dark:text-gray-400">PAYE is grouped by each employee's state of residence and pension by PFA. Set these on each employee's record. Due dates are a guide: check with your tax adviser.</p>
        </section>

        <section class="space-y-3" aria-labelledby="owed-title">
            <div>
                <h3 id="owed-title" class="text-base font-semibold text-gray-900 dark:text-white">What payroll owes</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Balances today. Approving a payroll adds to these; recording a payment below takes it off.</p>
            </div>
            <x-table caption="What payroll owes">
                <x-slot name="head">
                    <x-table.th>Account</x-table.th>
                    <x-table.th num>Owed</x-table.th>
                </x-slot>
                @foreach ($accounts as $account)
                    <tr>
                        <td><span class="tbl-muted">{{ $account->account_code }}</span> {{ $account->name }}</td>
                        <td class="num {{ (float) $account->current_balance != 0 ? '' : 'tbl-zero' }}">{{ $money($account->current_balance) }}</td>
                    </tr>
                @endforeach
            </x-table>
            <p class="text-xs text-gray-600 dark:text-gray-400">Accrued Salaries is net pay owed to staff; it clears when you mark payroll as paid.</p>
        </section>

            @can('edit payroll')
            <x-card class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Record a payment to the tax office or a fund</h3>
                <form method="POST" action="{{ route('payroll.liabilities.remit') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    <div>
                        <x-field name="account_code" label="Paying" type="select" required>
                            @foreach($accounts as $account)
                                @continue($account->account_code === \App\Services\AccountCodeService::resolve(auth()->user()->tenant_id, 'accrued_salaries'))
                                <option value="{{ $account->account_code }}" @selected(old('account_code') === $account->account_code)>{{ $account->name }} ({{ number_format($account->current_balance, 2) }} owed)</option>
                            @endforeach
                        </x-field>
                    </div>
                    <div>
                        <x-field name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="old('amount')" required />
                    </div>
                    <div>
                        <x-field name="period" label="For the month of" type="month" :value="old('period', $month->format('Y-m'))" help="For ITF, any month of the year being paid." />
                    </div>
                    <div>
                        <x-field name="paid_to" label="Paid to (state IRS or PFA)" :value="old('paid_to')" list="pay-to-options" maxlength="150" />
                        <datalist id="pay-to-options">
                            @foreach($payTo as $name)<option value="{{ $name }}">@endforeach
                        </datalist>
                    </div>
                    <div>
                        <x-field name="date" label="Date paid" type="date" :value="old('date', now()->toDateString())" required />
                    </div>
                    <div>
                        <x-field name="payment_method" label="Paid by" type="select">
                            @foreach($methods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', 'bank_transfer') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-field>
                    </div>
                    <div class="sm:col-span-2">
                        <x-field name="reference" label="Reference (receipt or remittance number)" :value="old('reference')" maxlength="100" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn-primary">Record payment</button>
                    </div>
                </form>
            </x-card>
            @endcan

            @if ($remittances->isNotEmpty())
            <section class="space-y-3" aria-labelledby="paid-title">
                <h3 id="paid-title" class="text-base font-semibold text-gray-900 dark:text-white">Recent payments</h3>
                <x-table caption="Recent payments" class="hidden md:block">
                    <x-slot name="head">
                        <x-table.th>Date</x-table.th>
                        <x-table.th>For</x-table.th>
                        <x-table.th>Period</x-table.th>
                        <x-table.th>Paid to</x-table.th>
                        <x-table.th>Reference</x-table.th>
                        <x-table.th num>Amount</x-table.th>
                    </x-slot>
                    @foreach ($remittances as $r)
                        <tr>
                            <td class="tbl-muted whitespace-nowrap">{{ $r->paid_on->format('j M Y') }}</td>
                            <td>{{ \App\Support\PayrollStatutory::LABELS[$r->body] ?? 'Account '.$r->account_code }}</td>
                            <td class="tbl-muted">{{ $r->period_start->format('M Y') }}{{ $r->period_start->isSameMonth($r->period_end) ? '' : ' to '.$r->period_end->format('M Y') }}</td>
                            <td class="{{ $r->paid_to ? '' : 'tbl-zero' }}">{{ $r->paid_to ?: '—' }}</td>
                            <td class="{{ $r->reference ? 'tbl-muted' : 'tbl-zero' }}">{{ $r->reference ?: '—' }}</td>
                            <td class="num">{{ $money($r->amount) }}</td>
                        </tr>
                    @endforeach
                </x-table>
                <ul class="space-y-2 md:hidden" aria-label="Recent payments">
                    @foreach ($remittances as $r)
                        <li class="rounded-lg border border-gray-200 bg-white px-3.5 py-3 dark:border-gray-700 dark:bg-gray-800">
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate font-semibold text-gray-900 dark:text-white">{{ \App\Support\PayrollStatutory::LABELS[$r->body] ?? 'Account '.$r->account_code }}</span>
                                <span class="whitespace-nowrap font-semibold tabular-nums text-gray-900 dark:text-white">{{ $money($r->amount) }}</span>
                            </span>
                            <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">{{ $r->paid_on->format('j M Y') }} · for {{ $r->period_start->format('M Y') }}{{ $r->paid_to ? ' · '.$r->paid_to : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
            @endif

            @can('edit payroll')
            <x-card class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Contribution rates</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Used when you run payroll. Pension is worked out on basic + housing + transport pay; NHF on basic pay, for staff marked as registered for NHF; NSITF and ITF on gross pay.
                    A pension or NHF line already in an employee's salary structure is used instead.
                    @unless($isNigerian) These apply only to businesses in Nigeria (see Company settings). @endunless
                </p>
                <form method="POST" action="{{ route('payroll.liabilities.settings') }}" class="space-y-4" x-data="{ enabled: {{ $settings['enabled'] ? 'true' : 'false' }} }">
                    @csrf
                    @method('PUT')
                    <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" x-model="enabled" class="rounded border-gray-300 dark:border-gray-600">
                        Add statutory contributions to payroll automatically
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" x-show="enabled">
                        <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100 sm:col-span-3">
                            <input type="hidden" name="pension_applies" value="0">
                            <input type="checkbox" name="pension_applies" value="1" @checked($settings['pension_applies']) class="rounded border-gray-300 dark:border-gray-600">
                            Pension (required with 3 or more staff)
                        </label>
                        <div><x-field name="pension_employee_rate" label="Employee pension %" type="number" step="0.01" min="0" max="100" :value="old('pension_employee_rate', $settings['pension_employee_rate'])" required /></div>
                        <div><x-field name="pension_employer_rate" label="Employer pension %" type="number" step="0.01" min="0" max="100" :value="old('pension_employer_rate', $settings['pension_employer_rate'])" required /></div>
                        <div><x-field name="nhf_rate" label="NHF % of basic" type="number" step="0.01" min="0" max="100" :value="old('nhf_rate', $settings['nhf_rate'])" required /></div>
                        <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                            <input type="hidden" name="nsitf_applies" value="0">
                            <input type="checkbox" name="nsitf_applies" value="1" @checked($settings['nsitf_applies']) class="rounded border-gray-300 dark:border-gray-600">
                            NSITF
                        </label>
                        <div class="sm:col-span-2"><x-field name="nsitf_rate" label="NSITF % of payroll" type="number" step="0.01" min="0" max="100" :value="old('nsitf_rate', $settings['nsitf_rate'])" required /></div>
                        <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                            <input type="hidden" name="itf_applies" value="0">
                            <input type="checkbox" name="itf_applies" value="1" @checked($settings['itf_applies']) class="rounded border-gray-300 dark:border-gray-600">
                            ITF (5 or more staff, or turnover of ₦50m or more)
                        </label>
                        <div class="sm:col-span-2"><x-field name="itf_rate" label="ITF % of payroll" type="number" step="0.01" min="0" max="100" :value="old('itf_rate', $settings['itf_rate'])" required /></div>
                    </div>
                    <button type="submit" class="btn-primary">Save rates</button>
                </form>
            </x-card>
            @endcan
    </div>
</x-app-layout>
