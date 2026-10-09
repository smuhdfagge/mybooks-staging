<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Issue Refund
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Invoice {{ $invoice->invoice_number }} - {{ $invoice->customer->name }}</p>
            </div>
            <a href="{{ route('invoices.show', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Invoice
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <!-- Invoice Summary -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Invoice Summary</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Invoice Total</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ number_format($invoice->total, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Amount Paid</p>
                            <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ number_format($invoice->amount_paid, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Already Refunded</p>
                            <p class="text-lg font-semibold text-amber-700 dark:text-amber-300">{{ number_format($invoice->total_refunded ?? 0, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Available to Refund</p>
                            <p class="text-lg font-semibold text-brand-600 dark:text-brand-300">{{ number_format($maxRefundable, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Refund Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('invoices.refunds.store', $invoice) }}" method="POST">
                        @csrf

                        <div class="space-y-6">
                            <!-- Refund Amount -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Refund Amount <span class="text-red-500">*</span>
                                    </label>
                                    <div class="mt-1 relative">
                                        <input type="number" 
                                               name="amount" 
                                               id="amount" 
                                               value="{{ old('amount', $maxRefundable) }}"
                                               min="0.01"
                                               max="{{ $maxRefundable }}"
                                               step="0.01"
                                               required
                                               class="block form-control" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Max: {{ number_format($maxRefundable, 2) }}</p>
                                    @error('amount')
                                        <p id="amount-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="refund_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Refund Date <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" 
                                           name="refund_date" 
                                           id="refund_date" 
                                           value="{{ old('refund_date', date('Y-m-d')) }}"
                                           max="{{ date('Y-m-d') }}"
                                           required
                                           class="mt-1 block form-control" @error('refund_date') aria-invalid="true" aria-describedby="refund_date-error" @enderror>
                                    @error('refund_date')
                                        <p id="refund_date-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Refund Method -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="refund_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Refund Method <span class="text-red-500">*</span>
                                    </label>
                                    <select name="refund_method" 
                                            id="refund_method" 
                                            required
                                            class="mt-1 block form-control" @error('refund_method') aria-invalid="true" aria-describedby="refund_method-error" @enderror>
                                        <option value="">Select Method</option>
                                        @foreach($methods as $value => $label)
                                            <option value="{{ $value }}" {{ old('refund_method') === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('refund_method')
                                        <p id="refund_method-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Reason for Refund
                                    </label>
                                    <select name="reason" 
                                            id="reason"
                                            class="mt-1 block form-control" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>
                                        <option value="">Select Reason</option>
                                        @foreach($reasons as $value => $label)
                                            <option value="{{ $value }}" {{ old('reason') === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reason')
                                        <p id="reason-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Reference -->
                            <div>
                                <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Reference Number
                                </label>
                                <input type="text" 
                                       name="reference" 
                                       id="reference" 
                                       value="{{ old('reference') }}"
                                       placeholder="e.g., Check number, transaction ID"
                                       class="mt-1 block form-control" @error('reference') aria-invalid="true" aria-describedby="reference-error" @enderror>
                                @error('reference')
                                    <p id="reference-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Notes -->
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Notes
                                </label>
                                <textarea name="notes" 
                                          id="notes" 
                                          rows="3"
                                          placeholder="Additional details about this refund..."
                                          class="mt-1 block form-control" @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p id="notes-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Quick Amount Buttons -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quick Amount</label>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" 
                                            data-set-value="amount" data-value="{{ $maxRefundable }}"
                                            class="px-3 py-1 text-sm bg-brand-100 dark:bg-brand-900 text-brand-700 dark:text-brand-300 rounded-md hover:bg-brand-200 dark:hover:bg-brand-800 transition">
                                        Full Refund ({{ number_format($maxRefundable, 2) }})
                                    </button>
                                    @if($maxRefundable > 0)
                                        <button type="button" 
                                                data-set-value="amount" data-value="{{ round($maxRefundable / 2, 2) }}"
                                                class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                            50% ({{ number_format($maxRefundable / 2, 2) }})
                                        </button>
                                        <button type="button" 
                                                data-set-value="amount" data-value="{{ round($maxRefundable * 0.25, 2) }}"
                                                class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                            25% ({{ number_format($maxRefundable * 0.25, 2) }})
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Warning -->
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Important</h3>
                                        <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                            This action will create a refund record and adjust the invoice balance. 
                                            A journal entry will be created to record the refund in your accounts.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('invoices.show', $invoice) }}" 
                                   class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    Cancel
                                </a>
                                <button type="submit" 
                                        class="px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                                    Process Refund
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
