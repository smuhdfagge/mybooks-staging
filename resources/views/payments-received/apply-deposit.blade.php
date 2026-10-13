<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Apply deposit {{ $paymentReceived->payment_number }}</x-slot>
            <x-slot name="description">Available balance: {{ number_format($paymentReceived->unused_amount, 2) }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <!-- Deposit Info Card -->
            <div class="bg-green-700 rounded-lg shadow-sm p-6 mb-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Available Deposit Balance</p>
                        <p class="text-4xl font-bold mt-1">{{ number_format($paymentReceived->unused_amount, 2) }}</p>
                        <p class="text-green-100 text-sm mt-2">From {{ $paymentReceived->customer->name }}</p>
                    </div>
                    <div class="bg-white/20 rounded-full p-4">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            @if($unpaidInvoices->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('payments-received.apply-deposit.store', $paymentReceived) }}" method="POST" class="p-6" x-data="applyDepositForm()">
                    @csrf

                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Select Invoice to Apply Deposit</h3>

                    <div class="space-y-4">
                        <!-- Invoice Selection -->
                        <div>
                            <label for="invoice_id" class="form-label">Invoice <span class="text-red-600 dark:text-red-300">*</span></label>
                            <select name="invoice_id" id="invoice_id" required x-model="selectedInvoice" @change="updateMaxAmount()"
                                class="form-control @error('invoice_id') border-red-500 @enderror" @error('invoice_id') aria-invalid="true" aria-describedby="invoice_id-error" @enderror>
                                <option value="">Select an invoice</option>
                                @foreach($unpaidInvoices as $invoice)
                                <option value="{{ $invoice->id }}" data-balance="{{ $invoice->balance_due }}">
                                    {{ $invoice->invoice_number }} - Due: {{ number_format($invoice->balance_due, 2) }} ({{ $invoice->due_date->format('j M Y') }})
                                </option>
                                @endforeach
                            </select>
                            @error('invoice_id')
                                <p id="invoice_id-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Amount -->
                        <div>
                            <label for="amount" class="form-label">Amount to Apply <span class="text-red-600 dark:text-red-300">*</span></label>
                            <div class="relative">
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" 
                                    :max="maxAmount" x-model="amount" required
                                    class="form-control @error('amount') border-red-500 @enderror" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedInvoice">
                                Maximum: <span x-text="formatMoney(maxAmount)"></span>
                            </p>
                            @error('amount')
                                <p id="amount-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" rows="2"
                                class="form-control"
                                placeholder="Optional notes about this application">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('payments-received.show', $paymentReceived) }}" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Apply deposit
                        </button>
                    </div>
                </form>
            </div>

            <!-- Unpaid Invoices Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Unpaid Invoices for {{ $paymentReceived->customer->name }}</h3>
                    <x-table :caption="'Unpaid invoices for '.$paymentReceived->customer->name">
<x-slot name="head">
                                    <x-table.th>Invoice</x-table.th>
                                    <x-table.th>Date</x-table.th>
                                    <x-table.th>Due date</x-table.th>
                                    <x-table.th num>Total</x-table.th>
                                    <x-table.th num>Balance due</x-table.th>
</x-slot>

                                @foreach($unpaidInvoices as $invoice)
                                <tr>
                                    <td>
                                        <a href="{{ route('invoices.show', $invoice) }}" class="tbl-link">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td>
                                        {{ $invoice->invoice_date->format('j M Y') }}
                                    </td>
                                    <td class="{{ $invoice->due_date < now() ? 'text-red-600 dark:text-red-300' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $invoice->due_date->format('j M Y') }}
                                        @if($invoice->due_date < now())
                                            <span class="text-xs">(Overdue)</span>
                                        @endif
                                    </td>
                                    <td class="num">
                                        @fig($invoice->total, false)
                                    </td>
                                    <td class="text-red-600 dark:text-red-300 num font-medium">
                                        @fig($invoice->balance_due, false)
                                    </td>
                                </tr>
                                @endforeach
</x-table>
                </div>
            </div>
            @else
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No unpaid invoices</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This customer has no unpaid invoices to apply the deposit to.</p>
                    <div class="mt-6">
                        <a href="{{ route('payments-received.show', $paymentReceived) }}" class="btn-secondary">
                            Back to Deposit
                        </a>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function applyDepositForm() {
            return {
                selectedInvoice: '{{ old('invoice_id') }}',
                amount: {{ old('amount', 0) }},
                maxAmount: {{ $paymentReceived->unused_amount }},
                invoiceBalances: {
                    @foreach($unpaidInvoices as $invoice)
                    '{{ $invoice->id }}': {{ $invoice->balance_due }},
                    @endforeach
                },
                availableDeposit: {{ $paymentReceived->unused_amount }},

                updateMaxAmount() {
                    if (this.selectedInvoice && this.invoiceBalances[this.selectedInvoice]) {
                        const invoiceBalance = this.invoiceBalances[this.selectedInvoice];
                        this.maxAmount = Math.min(invoiceBalance, this.availableDeposit);
                        this.amount = this.maxAmount;
                    } else {
                        this.maxAmount = this.availableDeposit;
                        this.amount = 0;
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
