<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit Payment {{ $paymentReceived->payment_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Update payment information</p>
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
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('payments-received.update', $paymentReceived) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Payment Number (Read-only) -->
                        <div>
                            <label for="payment_number" class="form-label">Payment Number</label>
                            <input type="text" id="payment_number" value="{{ $paymentReceived->payment_number }}" disabled
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 shadow-sm">
                        </div>

                        <!-- Payment Date -->
                        <div>
                            <x-field name="payment_date" label="Payment Date" type="date" :value="old('payment_date', $paymentReceived->payment_date?->format('Y-m-d'))" required />
                            @error('payment_date')
                                <p id="payment_date-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Customer (Read-only) -->
                        <div>
                            <label for="customer" class="form-label">Customer</label>
                            <input type="text" id="customer" value="{{ $paymentReceived->customer?->name ?? 'N/A' }}" disabled
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 shadow-sm">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Customer cannot be changed</p>
                        </div>

                        <!-- Invoice (Read-only) -->
                        <div>
                            <label for="invoice" class="form-label">Invoice</label>
                            <input type="text" id="invoice" value="{{ $paymentReceived->invoice?->invoice_number ?? 'General Payment' }}" disabled
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 shadow-sm">
                        </div>

                        <!-- Amount -->
                        <div>
                            <label for="amount" class="form-label">Amount <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">@currencySymbol</span>
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" value="{{ old('amount', $paymentReceived->amount) }}" required
                                    class="w-full pl-7 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                            </div>
                            @error('amount')
                                <p id="amount-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Payment Method -->
                        <div x-data="{ paymentMethod: '{{ old('payment_method', $paymentReceived->payment_method) }}' }">
                            <label for="payment_method" class="form-label">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" id="payment_method" required x-model="paymentMethod"
                                class="form-control @error('payment_method') border-red-500 @enderror" @error('payment_method') aria-invalid="true" aria-describedby="payment_method-error" @enderror>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="other">Other</option>
                            </select>
                            @error('payment_method')
                                <p id="payment_method-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <!-- Bank Account -->
                            <div x-show="paymentMethod === 'bank_transfer'" x-transition class="mt-4">
                                <label for="bank_id" class="form-label">Bank Account</label>
                                <select name="bank_id" id="bank_id"
                                    class="form-control @error('bank_id') border-red-500 @enderror" @error('bank_id') aria-invalid="true" aria-describedby="bank_id-error" @enderror>
                                    <option value="">Select Bank Account</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" {{ old('bank_id', $paymentReceived->bank_id) == $bank->id ? 'selected' : '' }}>
                                            {{ $bank->name }} ({{ $bank->account_number }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select which bank account received this payment</p>
                                @error('bank_id')
                                    <p id="bank_id-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Reference -->
                        <div class="md:col-span-2">
                            <x-field name="reference" label="Reference Number" :value="old('reference', $paymentReceived->reference)" placeholder="e.g., Check #, Transaction ID" />
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                class="form-control"
                                placeholder="Optional notes about this payment">{{ old('notes', $paymentReceived->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('payments-received.show', $paymentReceived) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
