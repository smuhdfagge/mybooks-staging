<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Withholding Tax') }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Tax deducted at source: what you take off vendor payments, and what customers take off theirs.</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <nav class="flex flex-wrap gap-2" aria-label="Withholding tax sections">
                @foreach(['deducted' => 'Deducted from vendors', 'credits' => 'Credits from customers', 'rates' => 'Rates and settings'] as $key => $label)
                    <a href="{{ route('withholding-tax.index', ['tab' => $key, 'month' => $month->format('Y-m')]) }}"
                       @if($tab === $key) aria-current="page" @endif
                       class="px-4 py-2 rounded-md text-sm font-medium {{ $tab === $key ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600' }}">{{ $label }}</a>
                @endforeach
            </nav>

            @if($tab === 'deducted')
                <x-card class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">WHT deducted in {{ $month->format('F Y') }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Due to the tax office by {{ $dueDate->format('j F Y') }}. WHT on individuals goes to their State IRS.</p>
                        </div>
                        <form method="GET" action="{{ route('withholding-tax.index') }}" class="flex items-end gap-2">
                            <input type="hidden" name="tab" value="deducted">
                            <div><x-field name="month" label="Month" type="month" :value="$month->format('Y-m')" /></div>
                            <button type="submit" class="btn-primary">Show</button>
                        </form>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="rounded-md bg-gray-50 dark:bg-gray-700/50 p-4">
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Deducted this month</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">@money($totalDeducted)</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 dark:bg-gray-700/50 p-4">
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Paid for this month</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">@money($remitted)</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 dark:bg-gray-700/50 p-4">
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">WHT owed in total (all months)</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">@money($payableBalance)</dd>
                        </div>
                    </dl>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vendor</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">TIN</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Payments</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WHT</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($byVendor as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $row->vendor?->name }}</td>
                                        <td class="px-3 py-2">{!! $row->vendor?->tax_number ? e($row->vendor->tax_number) : '<span class="text-amber-700 dark:text-amber-400">Missing</span>' !!}</td>
                                        <td class="px-3 py-2 text-right">{{ $row->count }}</td>
                                        <td class="px-3 py-2 text-right">@money($row->amount)</td>
                                        <td class="px-3 py-2 text-right font-medium">@money($row->wht)</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No WHT was deducted from vendor payments this month.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex gap-3 text-sm">
                        <a class="text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ route('withholding-tax.schedule', ['month' => $month->format('Y-m'), 'format' => 'pdf']) }}">Download schedule (PDF)</a>
                        <a class="text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ route('withholding-tax.schedule', ['month' => $month->format('Y-m'), 'format' => 'csv']) }}">Download schedule (CSV)</a>
                    </div>
                </x-card>

                @can('manage withholding-tax')
                <x-card class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Record paying WHT to the tax office</h3>
                    <form method="POST" action="{{ route('withholding-tax.remit') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @csrf
                        <div><x-field name="period" label="For the month of" type="month" :value="old('period', $month->format('Y-m'))" required /></div>
                        <div><x-field name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="old('amount', max(0, $totalDeducted - $remitted) ?: null)" required /></div>
                        <div><x-field name="date" label="Date paid" type="date" :value="old('date', now()->toDateString())" required /></div>
                        <div>
                            <x-field name="payment_method" label="Paid by" type="select">
                                @foreach($methods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method', 'bank_transfer') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-field>
                        </div>
                        <div><x-field name="paid_to" label="Paid to" :value="old('paid_to', 'Nigeria Revenue Service')" maxlength="150" /></div>
                        <div><x-field name="reference" label="Reference (receipt or payment number)" :value="old('reference')" maxlength="100" /></div>
                        <div class="sm:col-span-2"><button type="submit" class="btn-primary">Record payment</button></div>
                    </form>
                </x-card>
                @endcan

                @if($remittances->isNotEmpty())
                <x-card class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Recent WHT payments</h3>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        @foreach($remittances as $r)
                            <li class="py-2 flex justify-between gap-4">
                                <span>{{ $r->paid_on->format('j M Y') }} &middot; for {{ $r->period_start->format('M Y') }} &middot; {{ $r->paid_to }} {{ $r->reference ? '('.$r->reference.')' : '' }}</span>
                                <span class="font-medium">@money($r->amount)</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
                @endif
            @endif

            @if($tab === 'credits')
                <x-card class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">WHT credits from customers</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">When a customer takes WHT off a payment, they should send you a WHT credit note (certificate). Once you have it, the WHT can be set against your income tax.</p>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="rounded-md bg-gray-50 dark:bg-gray-700/50 p-4">
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Certificate received (can be claimed)</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">@money($creditTotals['received'])</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 dark:bg-gray-700/50 p-4">
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Awaiting certificate</dt>
                            <dd class="text-lg font-semibold text-amber-700 dark:text-amber-400">@money($creditTotals['awaiting'])</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 dark:bg-gray-700/50 p-4">
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Total WHT credits</dt>
                            <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">@money($creditTotals['total'])</dd>
                        </div>
                    </dl>
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3 text-sm">
                        <div class="flex gap-2">
                            @foreach(['' => 'All', 'awaiting' => 'Awaiting', 'received' => 'Received'] as $value => $label)
                                <a href="{{ route('withholding-tax.index', array_filter(['tab' => 'credits', 'status' => $value])) }}" class="px-3 py-1 rounded-md {{ ($status ?? '') === $value ? 'bg-gray-200 dark:bg-gray-700 font-semibold' : '' }} text-gray-700 dark:text-gray-300">{{ $label }}</a>
                            @endforeach
                        </div>
                        <div class="flex gap-3">
                            <a class="text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ route('withholding-tax.credits.export', ['format' => 'pdf']) }}">PDF</a>
                            <a class="text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ route('withholding-tax.credits.export', ['format' => 'csv']) }}">CSV</a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Payment</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WHT</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Certificate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($credits as $credit)
                                    <tr class="align-top">
                                        <td class="px-3 py-2">{{ $credit->deducted_on->format('j M Y') }}</td>
                                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $credit->customer?->name }}</td>
                                        <td class="px-3 py-2">
                                            @if($credit->payment)
                                                <a href="{{ route('payments-received.show', $credit->payment) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $credit->payment->payment_number }}</a>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium">@money($credit->amount)</td>
                                        <td class="px-3 py-2">
                                            @if($credit->status === 'received')
                                                <span class="text-green-700 dark:text-green-400 font-medium">Received</span>
                                                {{ $credit->certificate_number }} @if($credit->certificate_date)({{ $credit->certificate_date->format('j M Y') }})@endif
                                            @else
                                                <span class="text-amber-700 dark:text-amber-400 font-medium">Awaiting</span>
                                            @endif
                                            @can('manage withholding-tax')
                                                <details class="mt-1">
                                                    <summary class="cursor-pointer text-indigo-600 dark:text-indigo-400">{{ $credit->status === 'received' ? 'Change' : 'Record certificate' }}</summary>
                                                    <form method="POST" action="{{ route('withholding-tax.credits.update', $credit) }}" class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                        @csrf
                                                        @method('PUT')
                                                        <div><x-field name="certificate_number" id="certificate_number_{{ $credit->id }}" label="Certificate no." :value="$credit->certificate_number" maxlength="100" /></div>
                                                        <div><x-field name="certificate_date" id="certificate_date_{{ $credit->id }}" label="Date" type="date" :value="$credit->certificate_date?->toDateString()" /></div>
                                                        <div class="flex items-end"><button type="submit" class="btn-primary">Save</button></div>
                                                    </form>
                                                </details>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No WHT credits yet. Tick "The customer deducted withholding tax" when recording a payment.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $credits->links() }}</div>
                </x-card>
            @endif

            @if($tab === 'rates')
                <x-card class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">WHT rates</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">From the Deduction of Tax at Source (Withholding) Regulations 2024, for payees living in Nigeria. Check with your tax adviser and change them if the rules change. Leave a rate empty where it does not apply.</p>
                    <form method="POST" action="{{ route('withholding-tax.rates.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Transaction</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Company %</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Individual %</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">In use</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($rates as $rate)
                                        <tr>
                                            <td class="px-3 py-2"><input type="text" name="rates[{{ $rate->id }}][name]" value="{{ $rate->name }}" aria-label="Transaction" class="form-control" maxlength="150" required></td>
                                            <td class="px-3 py-2"><input type="number" name="rates[{{ $rate->id }}][rate_company]" value="{{ $rate->rate_company }}" aria-label="Company rate" class="form-control w-24" step="0.01" min="0" max="100"></td>
                                            <td class="px-3 py-2"><input type="number" name="rates[{{ $rate->id }}][rate_individual]" value="{{ $rate->rate_individual }}" aria-label="Individual rate" class="form-control w-24" step="0.01" min="0" max="100"></td>
                                            <td class="px-3 py-2">
                                                <input type="hidden" name="rates[{{ $rate->id }}][is_active]" value="0">
                                                <input type="checkbox" name="rates[{{ $rate->id }}][is_active]" value="1" @checked($rate->is_active) aria-label="In use" class="rounded border-gray-300 dark:border-gray-600">
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td class="px-3 py-2"><input type="text" name="new[name]" placeholder="Add another type..." aria-label="New transaction type" class="form-control" maxlength="150"></td>
                                        <td class="px-3 py-2"><input type="number" name="new[rate_company]" aria-label="New company rate" class="form-control w-24" step="0.01" min="0" max="100"></td>
                                        <td class="px-3 py-2"><input type="number" name="new[rate_individual]" aria-label="New individual rate" class="form-control w-24" step="0.01" min="0" max="100"></td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @can('manage withholding-tax')
                            <button type="submit" class="btn-primary mt-4">Save rates</button>
                        @endcan
                    </form>
                </x-card>

                <x-card class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Settings</h3>
                    <form method="POST" action="{{ route('withholding-tax.settings.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <label class="flex items-start gap-2 text-sm text-gray-900 dark:text-gray-100">
                            <input type="hidden" name="double_without_tin" value="0">
                            <input type="checkbox" name="double_without_tin" value="1" @checked($settings['double_without_tin']) class="mt-1 rounded border-gray-300 dark:border-gray-600">
                            <span>Double the rate when the vendor or customer has no TIN (the Regulations require this).</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-gray-900 dark:text-gray-100">
                            <input type="hidden" name="small_company" value="0">
                            <input type="checkbox" name="small_company" value="1" @checked($settings['small_company']) class="mt-1 rounded border-gray-300 dark:border-gray-600">
                            <span>We are a small company. Small companies need not deduct WHT from a supplier with a TIN when that month's payments to them are within the limit below. Check with your adviser whether you qualify.</span>
                        </label>
                        <div class="max-w-xs"><x-field name="small_company_threshold" label="Monthly limit per supplier" type="number" step="0.01" min="0" :value="old('small_company_threshold', $settings['small_company_threshold'])" required /></div>
                        @can('manage withholding-tax')
                            <button type="submit" class="btn-primary">Save settings</button>
                        @endcan
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
