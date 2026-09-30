<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Apply Deposit {{ $paymentReceived->payment_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Available balance: <span class="font-semibold text-green-600 dark:text-green-400">{{ number_format($paymentReceived->unused_amount, 2) }}</span>
                </p>
            </div>
            <a href="{{ route('payments-received.show', $paymentReceived) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <!-- Deposit Info Card -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-lg p-6 mb-6 text-white">
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
                            <label for="invoice_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice <span class="text-red-500">*</span></label>
                            <select name="invoice_id" id="invoice_id" required x-model="selectedInvoice" @change="updateMaxAmount()"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('invoice_id') border-red-500 @enderror">
                                <option value="">Select an invoice</option>
                                @foreach($unpaidInvoices as $invoice)
                                <option value="{{ $invoice->id }}" data-balance="{{ $invoice->balance_due }}">
                                    {{ $invoice->invoice_number }} - Due: {{ number_format($invoice->balance_due, 2) }} ({{ $invoice->due_date->format('M d, Y') }})
                                </option>
                                @endforeach
                            </select>
                            @error('invoice_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount to Apply <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" 
                                    :max="maxAmount" x-model="amount" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror">
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedInvoice">
                                Maximum: <span x-text="formatMoney(maxAmount)"></span>
                            </p>
                            @error('amount')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                            <textarea name="notes" id="notes" rows="2"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Optional notes about this application">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('payments-received.show', $paymentReceived) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Apply Deposit
                        </button>
                    </div>
                </form>
            </div>

            <!-- Unpaid Invoices Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Unpaid Invoices for {{ $paymentReceived->customer->name }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance Due</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($unpaidInvoices as $invoice)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $invoice->invoice_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm {{ $invoice->due_date < now() ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $invoice->due_date->format('M d, Y') }}
                                        @if($invoice->due_date < now())
                                            <span class="text-xs">(Overdue)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 text-right">
                                        {{ number_format($invoice->total, 2) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-red-600 dark:text-red-400 text-right font-medium">
                                        {{ number_format($invoice->balance_due, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
                        <a href="{{ route('payments-received.show', $paymentReceived) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
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
