<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">WHT Payable Schedule</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">WHT you deducted from vendors in {{ $month->format('F Y') }}, and paying it over</p>
            </div>
            <div class="flex flex-wrap gap-2 no-print">
                <a href="{{ route('withholding-tax.schedule.export', ['month' => $month->format('Y-m'), 'format' => 'pdf']) }}" class="inline-flex items-center px-3 py-2 bg-red-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">PDF</a>
                <a href="{{ route('withholding-tax.schedule.export', ['month' => $month->format('Y-m'), 'format' => 'csv']) }}" class="inline-flex items-center px-3 py-2 bg-green-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">CSV</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('withholding-tax._tabs')

            @php $canRemit = auth()->user()->can('remit withholding-tax'); @endphp

            <x-card class="p-6">
                <form method="GET" action="{{ route('withholding-tax.schedule') }}" class="flex flex-wrap items-end gap-3">
                    <div><x-field name="month" label="Month" type="month" :value="$month->format('Y-m')" /></div>
                    <button type="submit" class="btn-primary">Show</button>
                </form>
            </x-card>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach(['deducted' => 'WHT deducted this month', 'remitted' => 'Paid over for this month', 'outstanding' => 'Still to pay for this month'] as $key => $label)
                    <x-card class="p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">@money($totals[$key])</p>
                    </x-card>
                @endforeach
                <x-card class="p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">WHT payable now (all months)</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">@money($balance)</p>
                </x-card>
            </div>

            @forelse($groups as $group)
                <x-card>
                    <div class="px-6 pt-5 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $group['label'] }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $group['authority'] === 'state' ? 'WHT deducted from individuals' : 'WHT deducted from companies' }}.
                                Due by {{ $group['due']->format('j M Y') }}.
                            </p>
                        </div>
                        <div class="text-sm sm:text-right">
                            <p class="text-gray-600 dark:text-gray-400">Deducted <span class="font-semibold text-gray-900 dark:text-gray-100">@money($group['deducted'])</span></p>
                            <p class="text-gray-600 dark:text-gray-400">Paid over <span class="font-semibold text-gray-900 dark:text-gray-100">@money($group['remitted'])</span></p>
                            @if($group['outstanding'] > 0.005)
                                <p class="font-semibold {{ now()->greaterThan($group['due']->copy()->endOfDay()) ? 'text-red-600 dark:text-red-400' : 'text-yellow-700 dark:text-yellow-400' }}">Still to pay @money($group['outstanding'])</p>
                            @else
                                <p class="font-semibold text-green-700 dark:text-green-400">Paid in full</p>
                            @endif
                        </div>
                    </div>

                    <div class="p-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr class="text-left text-gray-600 dark:text-gray-300">
                                    <th scope="col" class="px-3 py-2">Vendor</th>
                                    <th scope="col" class="px-3 py-2">TIN</th>
                                    <th scope="col" class="px-3 py-2 text-right">Payments</th>
                                    <th scope="col" class="px-3 py-2 text-right">Amount before VAT</th>
                                    <th scope="col" class="px-3 py-2 text-right">WHT deducted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($group['vendors'] as $vendor)
                                    <tr>
                                        <td class="px-3 py-2">{{ $vendor['name'] }}</td>
                                        <td class="px-3 py-2">
                                            @if($vendor['tin']){{ $vendor['tin'] }}@else<span class="text-red-600 dark:text-red-400">No TIN</span>@endif
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ $vendor['payments'] }}</td>
                                        <td class="px-3 py-2 text-right">@money($vendor['base'])</td>
                                        <td class="px-3 py-2 text-right font-semibold">@money($vendor['wht'])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if($group['rows']->isNotEmpty())
                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm text-brand-600 dark:text-brand-300">Show each payment ({{ $group['rows']->count() }})</summary>
                            <table class="mt-3 min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead>
                                    <tr class="text-left text-gray-600 dark:text-gray-300">
                                        <th scope="col" class="px-3 py-2">Date</th>
                                        <th scope="col" class="px-3 py-2">Payment</th>
                                        <th scope="col" class="px-3 py-2">Vendor</th>
                                        <th scope="col" class="px-3 py-2">Bill</th>
                                        <th scope="col" class="px-3 py-2">Transaction type</th>
                                        <th scope="col" class="px-3 py-2 text-right">Before VAT</th>
                                        <th scope="col" class="px-3 py-2 text-right">Rate</th>
                                        <th scope="col" class="px-3 py-2 text-right">WHT</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                    @foreach($group['rows'] as $payment)
                                        <tr>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ $payment->payment_date->format('d M Y') }}</td>
                                            <td class="px-3 py-2"><a href="{{ route('payments-made.show', $payment) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $payment->payment_number }}</a></td>
                                            <td class="px-3 py-2">{{ $payment->vendor?->name }}</td>
                                            <td class="px-3 py-2">{{ $payment->bill?->bill_number ?? ($payment->is_advance ? 'Advance' : '-') }}</td>
                                            <td class="px-3 py-2">{{ $payment->whtCategory?->name ?? '-' }}</td>
                                            <td class="px-3 py-2 text-right">@money($payment->wht_base)</td>
                                            <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format((float) $payment->wht_rate, 2), '0'), '.') }}%</td>
                                            <td class="px-3 py-2 text-right">@money($payment->wht_amount)</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                        @endif

                        @if($group['remittances']->isNotEmpty())
                            <h4 class="mt-6 mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Payments to {{ $group['label'] }} for {{ $month->format('F Y') }}</h4>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                @foreach($group['remittances'] as $remittance)
                                    <li class="py-2 flex flex-wrap items-center justify-between gap-2 text-gray-900 dark:text-gray-100">
                                        <span>
                                            {{ $remittance->paid_on->format('j M Y') }}:
                                            <span class="font-semibold">@money($remittance->amount)</span>
                                            {{ $methods[$remittance->payment_method] ?? $remittance->payment_method }}{{ $remittance->bank ? ' from '.$remittance->bank->name : '' }}
                                            @if($remittance->reference)<span class="text-gray-500 dark:text-gray-400">(ref {{ $remittance->reference }})</span>@endif
                                        </span>
                                        @if($canRemit)
                                            <form method="POST" action="{{ route('withholding-tax.remittances.destroy', $remittance) }}" data-confirm="Delete this WHT payment? Its journal will be reversed.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    @if($canRemit && $group['outstanding'] > 0.005)
                        <form method="POST" action="{{ route('withholding-tax.remittances.store') }}" class="px-6 pb-6 pt-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                            @csrf
                            <input type="hidden" name="period" value="{{ $month->format('Y-m') }}">
                            <input type="hidden" name="authority" value="{{ $group['authority'] }}">
                            <input type="hidden" name="state" value="{{ $group['state'] }}">
                            <p class="sm:col-span-2 lg:col-span-5 text-sm text-gray-600 dark:text-gray-400">Record paying this WHT to {{ $group['label'] }}. It posts Dr WHT Payable, Cr the bank.</p>
                            <div><x-field name="amount" :id="'amount-'.$loop->index" label="Amount" type="number" step="0.01" min="0.01" :value="number_format($group['outstanding'], 2, '.', '')" required /></div>
                            <div><x-field name="paid_on" :id="'paid-on-'.$loop->index" label="Date paid" type="date" :value="now()->toDateString()" required /></div>
                            <div>
                                <x-field name="bank_id" :id="'bank-'.$loop->index" label="Paid from" type="select">
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" @selected($loop->first)>{{ $bank->name }}</option>
                                    @endforeach
                                    <option value="">Cash / no bank account</option>
                                </x-field>
                            </div>
                            <div>
                                <x-field name="payment_method" :id="'method-'.$loop->index" label="Paid by" type="select">
                                    @foreach($methods as $value => $label)
                                        <option value="{{ $value }}" @selected($value === 'bank_transfer')>{{ $label }}</option>
                                    @endforeach
                                </x-field>
                            </div>
                            <div><x-field name="reference" :id="'reference-'.$loop->index" label="Receipt / reference no." maxlength="100" /></div>
                            <div class="sm:col-span-2 lg:col-span-5"><button type="submit" class="btn-primary">Record WHT payment</button></div>
                        </form>
                    @endif
                </x-card>
            @empty
                <x-card class="p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400">No WHT was deducted from vendors in {{ $month->format('F Y') }}.</p>
                </x-card>
            @endforelse
        </div>
    </div>
</x-app-layout>
