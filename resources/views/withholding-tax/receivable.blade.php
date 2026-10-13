{{-- WHT credit notes receivable (tables plan T7): WHT customers took off their payments, to set against income tax. Amounts in ₦. --}}
@php
    $canManage = auth()->user()->can('manage withholding-tax');
    $exportQuery = array_filter($filters);
    $statusOptions = ['outstanding' => 'Awaiting credit note', 'received' => 'Credit note received', 'utilised' => 'Used against income tax'];
    $period = \Illuminate\Support\Carbon::parse($filters['start_date'])->format('j M Y').' to '.\Illuminate\Support\Carbon::parse($filters['end_date'])->format('j M Y');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="WHT credit notes receivable" description="WHT your customers deducted from their payments, to set against your income tax. Amounts in ₦.">
            <x-slot name="more">
                <x-table.menu-item :href="route('withholding-tax.setup')">Rates & settings</x-table.menu-item>
                <x-table.menu-item :href="route('payments-received.index')">Payments received</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                <x-table.dropdown label="Export" align="right">
                    <x-table.menu-item print>Print</x-table.menu-item>
                    <x-table.menu-item :href="route('withholding-tax.receivable.export', $exportQuery + ['format' => 'pdf'])">PDF file</x-table.menu-item>
                    <x-table.menu-item :href="route('withholding-tax.receivable.export', $exportQuery + ['format' => 'csv'])">CSV file</x-table.menu-item>
                </x-table.dropdown>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <x-report.sheet title="WHT credit notes receivable" :period="$period">
        @include('withholding-tax._tabs')

        <x-report.filters :action="route('withholding-tax.receivable')" button="Show">
            <x-report.date name="start_date" label="From" :value="$filters['start_date']" />
            <x-report.date name="end_date" label="To" :value="$filters['end_date']" />
            <x-report.pick name="customer_id" label="Customer" :options="$customers->pluck('name', 'id')" :value="$filters['customer_id']" all="All customers" />
            <x-report.pick name="status" label="Status" :options="$statusOptions" :value="$filters['status']" all="Any status" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Awaiting credit notes" :value="\App\Support\Figure::show($totals['outstanding'])" />
            <x-report.stat label="Credit notes in hand" :value="\App\Support\Figure::show($totals['received'])" hint="Ready to use" />
            <x-report.stat label="Used against income tax" :value="\App\Support\Figure::show($totals['utilised'])" />
            <x-report.stat label="Total WHT deducted" :value="\App\Support\Figure::show($totals['total'])" />
        </x-report.stats>

        @if ($payments->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No WHT deducted by customers in this period" text="When a customer pays you less WHT, the payment shows here until you get their credit note." />
            </div>
        @else
            <section class="space-y-3" aria-labelledby="by-customer-title">
                <h3 id="by-customer-title" class="text-base font-semibold text-gray-900 dark:text-white">By customer</h3>
                <x-table caption="WHT deducted, by customer">
                    <x-slot name="head">
                        <x-table.th>Customer</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Awaiting</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">In hand</x-table.th>
                        <x-table.th num class="hidden md:table-cell">Used</x-table.th>
                        <x-table.th num>Total</x-table.th>
                    </x-slot>
                    @foreach ($byCustomer as $row)
                        <tr>
                            <td>{{ $row['customer'] }}</td>
                            <td class="num hidden sm:table-cell {{ \App\Support\Figure::tone($row['outstanding']) }}">@fig($row['outstanding'], false)</td>
                            <td class="num hidden sm:table-cell {{ \App\Support\Figure::tone($row['received']) }}">@fig($row['received'], false)</td>
                            <td class="num hidden md:table-cell {{ \App\Support\Figure::tone($row['utilised']) }}">@fig($row['utilised'], false)</td>
                            <td class="num font-medium">@fig($row['total'], false)</td>
                        </tr>
                    @endforeach
                    <x-slot name="foot">
                        <tr>
                            <th scope="row">Total</th>
                            <td class="num hidden sm:table-cell">@fig($totals['outstanding'], false)</td>
                            <td class="num hidden sm:table-cell">@fig($totals['received'], false)</td>
                            <td class="num hidden md:table-cell">@fig($totals['utilised'], false)</td>
                            <td class="num">@fig($totals['total'], false)</td>
                        </tr>
                    </x-slot>
                </x-table>
            </section>

            <section class="space-y-3" aria-labelledby="payments-title">
                <h3 id="payments-title" class="text-base font-semibold text-gray-900 dark:text-white">Payments with WHT deducted</h3>
                <x-table caption="Payments with WHT deducted">
                    <x-slot name="head">
                        @if ($canManage)<th scope="col" class="tbl-check no-print"><span class="sr-only">Use</span></th>@endif
                        <x-table.th>Payment</x-table.th>
                        <x-table.th>Customer</x-table.th>
                        <x-table.th class="hidden lg:table-cell">Invoice</x-table.th>
                        <x-table.th num>WHT</x-table.th>
                        <x-table.th>Status</x-table.th>
                        <x-table.th>Credit note</x-table.th>
                    </x-slot>
                    @foreach ($payments as $payment)
                        @php $status = $payment->whtStatus(); @endphp
                        <tr>
                            @if($canManage)
                                <td class="tbl-check">
                                    @if($status === 'received')
                                        <input type="checkbox" name="payment_ids[]" value="{{ $payment->id }}" form="utilise-form" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800" aria-label="Use credit note for {{ $payment->payment_number }}">
                                    @endif
                                </td>
                            @endif
                            <td>
                                <a href="{{ route('payments-received.show', $payment) }}" class="tbl-link">{{ $payment->payment_number }}</a>
                                <div class="text-xs tbl-muted">{{ $payment->payment_date?->format('j M Y') }}</div>
                            </td>
                            <td>{{ $payment->customer?->name ?? '—' }}</td>
                            <td class="hidden lg:table-cell">{{ $payment->invoice?->invoice_number ?? '—' }}</td>
                            <td class="num">@fig($payment->wht_amount, false)</td>
                            <td>
                                <x-status-badge :status="['outstanding' => 'pending', 'received' => 'open', 'utilised' => 'completed'][$status] ?? 'draft'" :label="\App\Models\PaymentReceived::WHT_STATUS_LABELS[$status] ?? ''" />
                            </td>
                            <td>
                                @if($status === 'utilised')
                                    {{ $payment->wht_credit_note_number }} ({{ $payment->wht_credit_note_date?->format('j M Y') }})
                                @elseif($canManage)
                                    <form method="POST" action="{{ route('withholding-tax.credit-notes.store', $payment) }}" class="flex flex-wrap gap-2 items-center">
                                        @csrf
                                        <label class="sr-only" for="cn-number-{{ $payment->id }}">Credit note number</label>
                                        <input id="cn-number-{{ $payment->id }}" name="wht_credit_note_number" value="{{ $payment->wht_credit_note_number }}" placeholder="Credit note no." class="form-control w-36" required>
                                        <label class="sr-only" for="cn-date-{{ $payment->id }}">Credit note date</label>
                                        <input id="cn-date-{{ $payment->id }}" type="date" name="wht_credit_note_date" value="{{ $payment->wht_credit_note_date?->format('Y-m-d') }}" class="form-control w-40" required>
                                        <button type="submit" class="tbl-chip h-9">{{ $status === 'received' ? 'Update' : 'Record' }}</button>
                                    </form>
                                @else
                                    {{ $payment->wht_credit_note_number ?? '—' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </section>
        @endif

        @if($canManage && $totals['received'] > 0)
            <form id="utilise-form" method="POST" action="{{ route('withholding-tax.credit-notes.utilise') }}" class="no-print grid grid-cols-1 items-end gap-4 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-4 dark:border-gray-700 dark:bg-gray-800">
                @csrf
                <p class="md:col-span-4 text-sm text-gray-600 dark:text-gray-400">
                    Tick the credit notes in hand you are using against your income tax. This posts Dr Income Tax Payable, Cr WHT Credit Notes Receivable; ask your accountant to confirm the income tax figure.
                </p>
                <div><x-field name="utilisation_date" label="Date" type="date" :value="now()->toDateString()" required /></div>
                <div><x-field name="reference" label="Reference (e.g. tax assessment no.)" /></div>
                <div><x-field name="notes" label="Notes" /></div>
                <div><button type="submit" class="btn-primary w-full">Use against income tax</button></div>
            </form>
        @endif
    </x-report.sheet>
</x-app-layout>
