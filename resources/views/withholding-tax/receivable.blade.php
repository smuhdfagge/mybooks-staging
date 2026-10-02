<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">WHT Credit Notes Receivable</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">WHT your customers deducted from their payments, to set against your income tax</p>
            </div>
            <div class="flex flex-wrap gap-2 no-print">
                <a href="{{ route('withholding-tax.receivable.export', array_filter($filters) + ['format' => 'pdf']) }}" class="inline-flex items-center px-3 py-2 bg-red-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">PDF</a>
                <a href="{{ route('withholding-tax.receivable.export', array_filter($filters) + ['format' => 'csv']) }}" class="inline-flex items-center px-3 py-2 bg-green-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">CSV</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('withholding-tax._tabs')

            @php $canManage = auth()->user()->can('manage withholding-tax'); @endphp

            <x-card class="p-6">
                <form method="GET" action="{{ route('withholding-tax.receivable') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div><x-field name="start_date" label="From" type="date" :value="$filters['start_date']" /></div>
                    <div><x-field name="end_date" label="To" type="date" :value="$filters['end_date']" /></div>
                    <div>
                        <x-field name="customer_id" label="Customer" type="select">
                            <option value="">All customers</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) $filters['customer_id'] === (string) $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </x-field>
                    </div>
                    <div>
                        <x-field name="status" label="Status" type="select">
                            <option value="">All</option>
                            <option value="outstanding" @selected($filters['status'] === 'outstanding')>Outstanding (no credit note yet)</option>
                            <option value="received" @selected($filters['status'] === 'received')>Credit note received</option>
                            <option value="utilised" @selected($filters['status'] === 'utilised')>Used against income tax</option>
                        </x-field>
                    </div>
                    <div><button type="submit" class="btn-primary w-full">Show</button></div>
                </form>
            </x-card>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach(['outstanding' => 'Outstanding', 'received' => 'Credit notes in hand', 'utilised' => 'Used against income tax', 'total' => 'Total WHT deducted'] as $key => $label)
                    <x-card class="p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">@money($totals[$key])</p>
                    </x-card>
                @endforeach
            </div>

            <x-card title="By customer">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead>
                            <tr class="text-left text-gray-600 dark:text-gray-300">
                                <th scope="col" class="px-3 py-2">Customer</th>
                                <th scope="col" class="px-3 py-2 text-right">Outstanding</th>
                                <th scope="col" class="px-3 py-2 text-right">Received</th>
                                <th scope="col" class="px-3 py-2 text-right">Utilised</th>
                                <th scope="col" class="px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                            @forelse($byCustomer as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['customer'] }}</td>
                                    <td class="px-3 py-2 text-right">@money($row['outstanding'])</td>
                                    <td class="px-3 py-2 text-right">@money($row['received'])</td>
                                    <td class="px-3 py-2 text-right">@money($row['utilised'])</td>
                                    <td class="px-3 py-2 text-right font-semibold">@money($row['total'])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No WHT deducted by customers in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="Payments with WHT deducted">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead>
                            <tr class="text-left text-gray-600 dark:text-gray-300">
                                @if($canManage)<th scope="col" class="px-3 py-2"><span class="sr-only">Use</span></th>@endif
                                <th scope="col" class="px-3 py-2">Date</th>
                                <th scope="col" class="px-3 py-2">Payment</th>
                                <th scope="col" class="px-3 py-2">Customer</th>
                                <th scope="col" class="px-3 py-2">Invoice</th>
                                <th scope="col" class="px-3 py-2 text-right">WHT</th>
                                <th scope="col" class="px-3 py-2">Status</th>
                                <th scope="col" class="px-3 py-2">Credit note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                            @forelse($payments as $payment)
                                @php $status = $payment->whtStatus(); @endphp
                                <tr>
                                    @if($canManage)
                                        <td class="px-3 py-2">
                                            @if($status === 'received')
                                                <input type="checkbox" name="payment_ids[]" value="{{ $payment->id }}" form="utilise-form" aria-label="Use credit note for {{ $payment->payment_number }}">
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $payment->payment_date?->format('d M Y') }}</td>
                                    <td class="px-3 py-2"><a href="{{ route('payments-received.show', $payment) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $payment->payment_number }}</a></td>
                                    <td class="px-3 py-2">{{ $payment->customer?->name }}</td>
                                    <td class="px-3 py-2">{{ $payment->invoice?->invoice_number ?? '-' }}</td>
                                    <td class="px-3 py-2 text-right">@money($payment->wht_amount)</td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-0.5 rounded text-xs {{ ['outstanding' => 'bg-yellow-100 text-yellow-800', 'received' => 'bg-blue-100 text-blue-800', 'utilised' => 'bg-green-100 text-green-800'][$status] ?? '' }}">
                                            {{ ['outstanding' => 'Outstanding', 'received' => 'Received', 'utilised' => 'Utilised'][$status] ?? '' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        @if($status === 'utilised')
                                            {{ $payment->wht_credit_note_number }} ({{ $payment->wht_credit_note_date?->format('d M Y') }})
                                        @elseif($canManage)
                                            <form method="POST" action="{{ route('withholding-tax.credit-notes.store', $payment) }}" class="flex flex-wrap gap-2 items-center">
                                                @csrf
                                                <label class="sr-only" for="cn-number-{{ $payment->id }}">Credit note number</label>
                                                <input id="cn-number-{{ $payment->id }}" name="wht_credit_note_number" value="{{ $payment->wht_credit_note_number }}" placeholder="Credit note no." class="form-control w-36" required>
                                                <label class="sr-only" for="cn-date-{{ $payment->id }}">Credit note date</label>
                                                <input id="cn-date-{{ $payment->id }}" type="date" name="wht_credit_note_date" value="{{ $payment->wht_credit_note_date?->format('Y-m-d') }}" class="form-control w-40" required>
                                                <button type="submit" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">{{ $status === 'received' ? 'Update' : 'Record' }}</button>
                                            </form>
                                        @else
                                            {{ $payment->wht_credit_note_number ?? '-' }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No payments with WHT in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($canManage && $totals['received'] > 0)
                    <form id="utilise-form" method="POST" action="{{ route('withholding-tax.credit-notes.utilise') }}" class="px-6 pb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end border-t border-gray-200 dark:border-gray-700 pt-4">
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
            </x-card>
        </div>
    </div>
</x-app-layout>
