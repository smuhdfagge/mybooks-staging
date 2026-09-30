<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Record Payment
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Record a new payment from a customer</p>
            </div>
            <a href="{{ route('payments-received.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
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
                <form action="{{ route('payments-received.store') }}" method="POST" class="p-6" x-data="paymentForm()">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Payment Number -->
                        <div>
                            <label for="payment_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Number</label>
                            <input type="text" id="payment_number" value="{{ $paymentNumber }}" disabled
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 shadow-sm">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-generated</p>
                        </div>

                        <!-- Payment Date -->
                        <div>
                            <label for="payment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Date <span class="text-red-500">*</span></label>
                            <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('payment_date') border-red-500 @enderror">
                            @error('payment_date')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Customer -->
                        <div x-data="searchableSelect({
                            items: @js($customers->map(fn ($customer) => ['id' => (string) $customer->id, 'name' => (string) $customer->name])->values()),
                            selectedId: '{{ old('customer_id', $invoice?->customer_id) }}',
                            onSelect: (id) => { selectedCustomer = id; filterInvoices(); filterDeposits(); }
                        })" class="relative">
                            <label for="customer_search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Customer <span class="text-red-500">*</span></label>
                            <input type="hidden" name="customer_id" :value="selectedId" required>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    id="customer_search"
                                    x-model="search"
                                    @focus="open = true"
                                    @click="open = true"
                                    @input="open = true"
                                    @keydown.escape="open = false"
                                    @keydown.arrow-down.prevent="highlightNext()"
                                    @keydown.arrow-up.prevent="highlightPrev()"
                                    @keydown.enter.prevent="selectHighlighted()"
                                    placeholder="Search customers..."
                                    autocomplete="off"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('customer_id') border-red-500 @enderror">
                                <button type="button" @click="open = !open" class="absolute inset-y-0 right-0 flex items-center pr-2">
                                    <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <div 
                                x-show="open" 
                                @click.away="open = false"
                                x-transition
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                <template x-for="(item, index) in filteredItems" :key="item.id">
                                    <div 
                                        @click="selectItem(item)"
                                        @mouseenter="highlightedIndex = index"
                                        :class="{ 'bg-indigo-600 text-white': highlightedIndex === index, 'text-gray-900 dark:text-gray-100': highlightedIndex !== index }"
                                        class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white">
                                        <span x-text="item.name" class="block truncate"></span>
                                        <span x-show="selectedId == item.id" class="absolute inset-y-0 right-0 flex items-center pr-4">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </div>
                                </template>
                                <div x-show="filteredItems.length === 0" class="py-2 px-3 text-gray-500 dark:text-gray-400 text-sm">
                                    No customers found
                                </div>
                            </div>
                            @error('customer_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Invoice (Optional) -->
                        <div>
                            <label for="invoice_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice (Optional)</label>
                            <select name="invoice_id" id="invoice_id" x-model="selectedInvoice" @change="updateAmount()"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">No specific invoice</option>
                                <template x-for="inv in filteredInvoices" :key="inv.id">
                                    <option :value="inv.id" x-text="inv.invoice_number + ' - ' + formatMoney(inv.balance_due) + ' due'"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="!selectedCustomer">Select a customer to see their unpaid invoices</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedCustomer && filteredInvoices.length === 0">No unpaid invoices for this customer</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedCustomer && filteredInvoices.length > 0">Leave empty for general payment</p>
                        </div>

                        <!-- Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" value="{{ old('amount', $invoice?->balance_due) }}" required placeholder="0.00"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror">
                            </div>
                            @error('amount')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Payment Method -->
                        <div x-data="{ paymentMethod: '{{ old('payment_method', '') }}' }">
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" id="payment_method" required x-model="paymentMethod"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('payment_method') border-red-500 @enderror">
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="other">Other</option>
                            </select>
                            @error('payment_method')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <!-- Bank Account -->
                            <div x-show="paymentMethod === 'bank_transfer'" x-transition class="mt-4">
                                <label for="bank_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank Account</label>
                                <select name="bank_id" id="bank_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('bank_id') border-red-500 @enderror">
                                    <option value="">Select Bank Account</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                                            {{ $bank->name }} ({{ $bank->account_number }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select which bank account received this payment</p>
                                @error('bank_id')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Reference -->
                        <div class="md:col-span-2">
                            <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference Number</label>
                            <input type="text" name="reference" id="reference" value="{{ old('reference') }}"
                                placeholder="e.g., Check #, Transaction ID"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Is Deposit Checkbox -->
                        <div class="md:col-span-2">
                            <div class="flex items-center">
                                <input type="checkbox" name="is_deposit" id="is_deposit" value="1" x-model="isDeposit"
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700">
                                <label for="is_deposit" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Record as Customer Deposit (Advance Payment)
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="isDeposit">
                                This amount will be held as a deposit and can be applied to future invoices.
                            </p>
                        </div>

                        <!-- Apply Existing Deposit Section -->
                        <div class="md:col-span-2" x-show="!isDeposit && selectedCustomer && availableDeposits.length > 0">
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4">
                                <div class="flex items-center mb-3">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-green-800 dark:text-green-300">
                                        Customer has available deposit: <span class="font-bold" x-text="formatMoney(totalAvailableDeposit)"></span>
                                    </span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="use_deposit" x-model="useDeposit"
                                        class="rounded border-gray-300 dark:border-gray-600 text-green-600 shadow-sm focus:border-green-500 focus:ring-green-500 dark:bg-gray-700">
                                    <label for="use_deposit" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Apply deposit to this invoice
                                    </label>
                                </div>
                                <div x-show="useDeposit" class="mt-3 space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Deposit</label>
                                        <select name="apply_deposit_id" x-model="selectedDepositId" @change="updateDepositAmount()"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">Select a deposit to apply</option>
                                            <template x-for="dep in availableDeposits" :key="dep.id">
                                                <option :value="dep.id" x-text="dep.payment_number + ' - ' + formatMoney(dep.unused_amount) + ' available'"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount to Apply from Deposit</label>
                                        <input type="number" name="deposit_amount" id="deposit_amount" step="0.01" min="0" 
                                            :max="maxDepositAmount" x-model="depositAmountToApply"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedDepositId">
                                            Maximum: <span x-text="formatMoney(maxDepositAmount)"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Optional notes about this payment">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('payments-received.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span x-text="isDeposit ? 'Record Deposit' : 'Record Payment'">Record Payment</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function searchableSelect(config) {
            return {
                items: config.items || [],
                selectedId: config.selectedId || '',
                search: '',
                open: false,
                highlightedIndex: 0,
                onSelect: config.onSelect || null,

                init() {
                    if (this.selectedId) {
                        const selected = this.items.find(item => item.id == this.selectedId);
                        if (selected) {
                            this.search = selected.name;
                        }
                    }
                },

                get filteredItems() {
                    if (!this.search) return this.items;
                    return this.items.filter(item => 
                        item.name.toLowerCase().includes(this.search.toLowerCase())
                    );
                },

                selectItem(item) {
                    this.selectedId = item.id;
                    this.search = item.name;
                    this.open = false;
                    if (this.onSelect) this.onSelect(item.id);
                },

                highlightNext() {
                    if (this.highlightedIndex < this.filteredItems.length - 1) {
                        this.highlightedIndex++;
                    }
                },

                highlightPrev() {
                    if (this.highlightedIndex > 0) {
                        this.highlightedIndex--;
                    }
                },

                selectHighlighted() {
                    if (this.filteredItems.length > 0) {
                        this.selectItem(this.filteredItems[this.highlightedIndex]);
                    }
                }
            }
        }

        function paymentForm() {
            return {
                selectedCustomer: '{{ old('customer_id', $invoice?->customer_id ?? '') }}',
                selectedInvoice: '{{ old('invoice_id', $invoice?->id ?? '') }}',
                allInvoices: @json($unpaidInvoices),
                allDeposits: @json($customerDeposits ?? []),
                filteredInvoices: [],
                availableDeposits: [],
                isDeposit: {{ old('is_deposit') ? 'true' : 'false' }},
                useDeposit: false,
                selectedDepositId: '',
                depositAmountToApply: 0,
                maxDepositAmount: 0,

                init() {
                    this.filterInvoices();
                    this.filterDeposits();
                },

                get totalAvailableDeposit() {
                    return this.availableDeposits.reduce((sum, dep) => sum + parseFloat(dep.unused_amount), 0);
                },

                filterInvoices() {
                    if (this.selectedCustomer) {
                        this.filteredInvoices = this.allInvoices.filter(inv => inv.customer_id == this.selectedCustomer);
                    } else {
                        this.filteredInvoices = [];
                    }
                    // Reset selected invoice if not in filtered list
                    if (this.selectedInvoice && !this.filteredInvoices.find(inv => inv.id == this.selectedInvoice)) {
                        this.selectedInvoice = '';
                    }
                },

                filterDeposits() {
                    if (this.selectedCustomer) {
                        this.availableDeposits = this.allDeposits.filter(dep => dep.customer_id == this.selectedCustomer);
                    } else {
                        this.availableDeposits = [];
                    }
                    // Reset deposit selection
                    this.selectedDepositId = '';
                    this.depositAmountToApply = 0;
                    this.useDeposit = false;
                },

                updateDepositAmount() {
                    if (this.selectedDepositId) {
                        const deposit = this.availableDeposits.find(dep => dep.id == this.selectedDepositId);
                        const invoice = this.filteredInvoices.find(inv => inv.id == this.selectedInvoice);
                        if (deposit && invoice) {
                            this.maxDepositAmount = Math.min(parseFloat(deposit.unused_amount), parseFloat(invoice.balance_due));
                            this.depositAmountToApply = this.maxDepositAmount;
                        } else if (deposit) {
                            this.maxDepositAmount = parseFloat(deposit.unused_amount);
                            this.depositAmountToApply = this.maxDepositAmount;
                        }
                    } else {
                        this.maxDepositAmount = 0;
                        this.depositAmountToApply = 0;
                    }
                },

                updateAmount() {
                    if (this.selectedInvoice) {
                        const invoice = this.filteredInvoices.find(inv => inv.id == this.selectedInvoice);
                        if (invoice) {
                            document.getElementById('amount').value = parseFloat(invoice.balance_due).toFixed(2);
                        }
                    }
                    // Also update max deposit amount if a deposit is selected
                    this.updateDepositAmount();
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
