{{-- WHT payable schedule (tables plan T7): WHT deducted from vendors in a month, by tax office, and paying it over. Amounts in ₦. --}}
@php
    $canRemit = auth()->user()->can('remit withholding-tax');
    $exportQuery = ['month' => $month->format('Y-m')];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="WHT payable schedule" :description="'WHT you deducted from vendors in '.$month->format('F Y').', and paying it over. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('withholding-tax.setup')">Rates & settings</x-table.menu-item>
                <x-table.menu-item :href="route('payments-made.index')">Payments made</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                <x-table.dropdown label="Export" align="right">
                    <x-table.menu-item print>Print</x-table.menu-item>
                    <x-table.menu-item :href="route('withholding-tax.schedule.export', $exportQuery + ['format' => 'pdf'])">PDF file</x-table.menu-item>
                    <x-table.menu-item :href="route('withholding-tax.schedule.export', $exportQuery + ['format' => 'csv'])">CSV file</x-table.menu-item>
                </x-table.dropdown>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <x-report.sheet title="WHT payable schedule" :period="$month->format('F Y')">
        @include('withholding-tax._tabs')

        <x-report.filters :action="route('withholding-tax.schedule')" button="Show">
            <x-report.date name="month" label="Month" type="month" :value="$month->format('Y-m')" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="WHT deducted this month" :value="\App\Support\Figure::show($totals['deducted'])" />
            <x-report.stat label="Paid over for this month" :value="\App\Support\Figure::show($totals['remitted'])" />
            <x-report.stat label="Still to pay for this month" :value="\App\Support\Figure::show($totals['outstanding'])" :tone="$totals['outstanding'] > 0.005 ? 'bad' : null" />
            <x-report.stat label="WHT payable now" :value="\App\Support\Figure::show($balance)" hint="All months" />
        </x-report.stats>

        @forelse ($groups as $group)
            @php $late = now()->greaterThan($group['due']->copy()->endOfDay()); @endphp
            <section class="space-y-3" aria-labelledby="office-{{ $loop->index }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 id="office-{{ $loop->index }}" class="text-base font-semibold text-gray-900 dark:text-white">{{ $group['label'] }}</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $group['authority'] === 'state' ? 'WHT deducted from individuals' : 'WHT deducted from companies' }}.
                            <span class="{{ $late && $group['outstanding'] > 0.005 ? 'tbl-late' : '' }}">Due by {{ $group['due']->format('j M Y') }}.</span>
                        </p>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 sm:text-right">
                        Deducted <span class="font-semibold text-gray-900 dark:text-white">@money($group['deducted'])</span> ·
                        paid over <span class="font-semibold text-gray-900 dark:text-white">@money($group['remitted'])</span> ·
                        @if ($group['outstanding'] > 0.005)
                            <span class="font-semibold {{ $late ? 'tbl-late' : 'text-amber-800 dark:text-amber-300' }}">Still to pay @money($group['outstanding'])</span>
                        @else
                            <span class="font-semibold text-green-800 dark:text-green-300">Paid in full</span>
                        @endif
                    </p>
                </div>

                <x-table :caption="'WHT deducted, by vendor: '.$group['label']">
                    <x-slot name="head">
                        <x-table.th>Vendor</x-table.th>
                        <x-table.th class="hidden sm:table-cell">TIN</x-table.th>
                        <x-table.th num class="hidden md:table-cell">Payments</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Amount before VAT</x-table.th>
                        <x-table.th num>WHT deducted</x-table.th>
                    </x-slot>
                    @foreach ($group['vendors'] as $vendor)
                        <tr>
                            <td>
                                {{ $vendor['name'] }}
                                <div class="text-xs sm:hidden {{ $vendor['tin'] ? 'tbl-muted' : 'tbl-late' }}">{{ $vendor['tin'] ?: 'No TIN' }}</div>
                            </td>
                            <td class="hidden sm:table-cell">
                                @if ($vendor['tin']){{ $vendor['tin'] }}@else<span class="tbl-late">No TIN</span>@endif
                            </td>
                            <td class="num hidden md:table-cell">{{ $vendor['payments'] }}</td>
                            <td class="num hidden sm:table-cell">@fig($vendor['base'], false)</td>
                            <td class="num font-medium">@fig($vendor['wht'], false)</td>
                        </tr>
                    @endforeach
                    <x-slot name="foot">
                        <tr>
                            <th scope="row">Total</th>
                            <td class="hidden sm:table-cell"></td>
                            <td class="num hidden md:table-cell">{{ collect($group['vendors'])->sum('payments') }}</td>
                            <td class="num hidden sm:table-cell">@fig(collect($group['vendors'])->sum('base'), false)</td>
                            <td class="num">@fig($group['deducted'], false)</td>
                        </tr>
                    </x-slot>
                </x-table>

                @if ($group['rows']->isNotEmpty())
                    <details class="no-print">
                        <summary class="cursor-pointer text-sm font-medium tbl-link">Show each payment ({{ $group['rows']->count() }})</summary>
                        <div class="mt-3">
                            <x-table :caption="'Each payment with WHT: '.$group['label']">
                                <x-slot name="head">
                                    <x-table.th>Payment</x-table.th>
                                    <x-table.th class="hidden sm:table-cell">Vendor</x-table.th>
                                    <x-table.th class="hidden md:table-cell">Bill</x-table.th>
                                    <x-table.th class="hidden lg:table-cell">Transaction type</x-table.th>
                                    <x-table.th num class="hidden sm:table-cell">Before VAT</x-table.th>
                                    <x-table.th num class="hidden md:table-cell">Rate</x-table.th>
                                    <x-table.th num>WHT</x-table.th>
                                </x-slot>
                                @foreach ($group['rows'] as $payment)
                                    <tr>
                                        <td>
                                            <a href="{{ route('payments-made.show', $payment) }}" class="tbl-link">{{ $payment->payment_number }}</a>
                                            <div class="text-xs tbl-muted">{{ $payment->payment_date->format('j M Y') }}</div>
                                        </td>
                                        <td class="hidden sm:table-cell">{{ $payment->vendor?->name ?? '—' }}</td>
                                        <td class="hidden md:table-cell">{{ $payment->bill?->bill_number ?? ($payment->is_advance ? 'Advance' : '—') }}</td>
                                        <td class="hidden lg:table-cell rpt-wrap max-w-[16rem]">{{ $payment->whtCategory?->name ?? '—' }}</td>
                                        <td class="num hidden sm:table-cell">@fig($payment->wht_base, false)</td>
                                        <td class="num hidden md:table-cell">{{ rtrim(rtrim(number_format((float) $payment->wht_rate, 2), '0'), '.') }}%</td>
                                        <td class="num">@fig($payment->wht_amount, false)</td>
                                    </tr>
                                @endforeach
                            </x-table>
                        </div>
                    </details>
                @endif

                @if ($group['remittances']->isNotEmpty())
                    <x-table :caption="'Paid to '.$group['label'].' for '.$month->format('F Y')">
                        <x-slot name="head">
                            <x-table.th>Paid on</x-table.th>
                            <x-table.th class="hidden sm:table-cell">How</x-table.th>
                            <x-table.th class="hidden md:table-cell">Reference</x-table.th>
                            <x-table.th num>Amount</x-table.th>
                            @if ($canRemit)
                                <th scope="col" class="tbl-menu no-print"><span class="sr-only">Actions</span></th>
                            @endif
                        </x-slot>
                        @foreach ($group['remittances'] as $remittance)
                            <tr>
                                <td>{{ $remittance->paid_on->format('j M Y') }}</td>
                                <td class="hidden sm:table-cell">{{ $methods[$remittance->payment_method] ?? $remittance->payment_method }}{{ $remittance->bank ? ' from '.$remittance->bank->name : '' }}</td>
                                <td class="hidden md:table-cell {{ $remittance->reference ? '' : 'tbl-zero' }}">{{ $remittance->reference ?: '—' }}</td>
                                <td class="num">@fig($remittance->amount, false)</td>
                                @if ($canRemit)
                                    <td class="tbl-menu no-print">
                                        <x-table.dropdown sr-label="Actions for this payment">
                                            <x-table.menu-item danger :post="route('withholding-tax.remittances.destroy', $remittance)" method="DELETE" confirm="Delete this WHT payment? Its journal will be reversed.">Delete</x-table.menu-item>
                                        </x-table.dropdown>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </x-table>
                @endif

                @if($canRemit && $group['outstanding'] > 0.005)
                    <form method="POST" action="{{ route('withholding-tax.remittances.store') }}" class="no-print rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
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
            </section>
        @empty
            <div class="tbl-wrap">
                <x-table.empty title="Nothing to pay over" :text="'No WHT was deducted from vendors in '.$month->format('F Y').'.'" />
            </div>
        @endforelse
    </x-report.sheet>
</x-app-layout>
