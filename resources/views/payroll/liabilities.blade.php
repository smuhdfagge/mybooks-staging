<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Payroll Liabilities') }}</h2>
            <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">&larr; Back to Payroll</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Tax pack 1: each statutory body for the month --}}
            <x-card class="p-6">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Remittances for {{ $month->format('F Y') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">From approved and paid payroll for the month. Download a schedule to send with each payment.</p>
                    </div>
                    <form method="GET" action="{{ route('payroll.liabilities') }}" class="flex items-end gap-2">
                        <div>
                            <x-field name="month" label="Month" type="month" :value="$month->format('Y-m')" />
                        </div>
                        <button type="submit" class="btn-primary">Show</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pay to</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Due by</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">From payroll</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Paid</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Still to pay</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Schedule</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($summary as $row)
                                <tr>
                                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                        {{ $row->label }}
                                        @if($row->body === 'itf')<span class="block text-xs text-gray-500 dark:text-gray-400">Paid yearly: {{ $row->from->format('M') }} to {{ $row->to->format('M Y') }}</span>@endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300" title="{{ $row->due_rule }}">
                                        {{ $row->due_date->format('j M Y') }}
                                        @if($row->outstanding > 0 && $row->due_date->isPast())<span class="ml-1 text-xs font-semibold text-red-600 dark:text-red-400">Overdue</span>@endif
                                    </td>
                                    <td class="px-3 py-2 text-right">@money($row->due)</td>
                                    <td class="px-3 py-2 text-right">@money($row->paid)</td>
                                    <td class="px-3 py-2 text-right font-medium {{ $row->outstanding > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }}">@money($row->outstanding)</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <a href="{{ route('payroll.liabilities.schedule', ['body' => $row->body, 'month' => $month->format('Y-m'), 'format' => 'pdf']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">PDF</a>
                                        <span class="text-gray-500 dark:text-gray-400">|</span>
                                        <a href="{{ route('payroll.liabilities.schedule', ['body' => $row->body, 'month' => $month->format('Y-m'), 'format' => 'csv']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">CSV</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">PAYE is grouped by each employee's state of residence and pension by PFA. Set these on each employee's record. Due dates are a guide: check with your tax adviser.</p>
            </x-card>

            <x-card class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">What payroll owes</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Balances today. Approving a payroll adds to these; recording a payment below takes it off.</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Owed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($accounts as $account)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $account->account_code }} - {{ $account->name }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($account->current_balance, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">Accrued Salaries is net pay owed to staff; it clears when you mark payroll as paid.</p>
            </x-card>

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

            @if($remittances->isNotEmpty())
            <x-card class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Recent payments</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">For</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Period</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Paid to</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reference</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($remittances as $r)
                                <tr>
                                    <td class="px-3 py-2">{{ $r->paid_on->format('j M Y') }}</td>
                                    <td class="px-3 py-2">{{ \App\Support\PayrollStatutory::LABELS[$r->body] ?? 'Account '.$r->account_code }}</td>
                                    <td class="px-3 py-2">{{ $r->period_start->format('M Y') }}@if(! $r->period_start->isSameMonth($r->period_end)) to {{ $r->period_end->format('M Y') }}@endif</td>
                                    <td class="px-3 py-2">{{ $r->paid_to }}</td>
                                    <td class="px-3 py-2">{{ $r->reference }}</td>
                                    <td class="px-3 py-2 text-right">@money($r->amount)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
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
    </div>
</x-app-layout>
