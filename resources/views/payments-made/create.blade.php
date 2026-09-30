<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Record Payment
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Record a new payment to a vendor</p>
            </div>
            <a href="{{ route('payments-made.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
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
                <form action="{{ route('payments-made.store') }}" method="POST" class="p-6" x-data="paymentForm()">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Payment Number -->
                        <div>
                            <label for="payment_number" class="form-label">Payment Number</label>
                            <input type="text" id="payment_number" value="{{ $paymentNumber }}" disabled
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 shadow-sm">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-generated</p>
                        </div>

                        <!-- Payment Date -->
                        <div>
                            <x-field name="payment_date" label="Payment Date" type="date" :value="old('payment_date', date('Y-m-d'))" required />
                            @error('payment_date')
                                <p id="payment_date-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Vendor -->
                        <div x-data="searchableSelect({
                            items: @js($vendors->map(fn ($vendor) => ['id' => (string) $vendor->id, 'name' => (string) $vendor->name])->values()),
                            selectedId: '{{ old('vendor_id', $bill?->vendor_id) }}',
                            onSelect: (id) => { selectedVendor = id; filterBills(); }
                        })" class="relative">
                            <label for="vendor_search" class="form-label">Vendor <span class="text-red-500">*</span></label>
                            <input type="hidden" name="vendor_id" :value="selectedId" required @error('vendor_id') aria-invalid="true" aria-describedby="vendor_id-error" @enderror>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    id="vendor_search"
                                    x-model="search"
                                    @focus="open = true"
                                    @click="open = true"
                                    @input="open = true"
                                    @keydown.escape="open = false"
                                    @keydown.arrow-down.prevent="highlightNext()"
                                    @keydown.arrow-up.prevent="highlightPrev()"
                                    @keydown.enter.prevent="selectHighlighted()"
                                    placeholder="Search vendors..."
                                    autocomplete="off"
                                    class="form-control @error('vendor_id') border-red-500 @enderror">
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
                                    No vendors found
                                </div>
                            </div>
                            @error('vendor_id')
                                <p id="vendor_id-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Bill (Optional) -->
                        <div>
                            <label for="bill_id" class="form-label">Bill (Optional)</label>
                            <select name="bill_id" id="bill_id" x-model="selectedBill" @change="updateAmount()"
                                class="form-control">
                                <option value="">No specific bill</option>
                                <template x-for="b in filteredBills" :key="b.id">
                                    <option :value="b.id" x-text="b.bill_number + ' - ' + formatMoney(b.balance_due) + ' due'"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="!selectedVendor">Select a vendor to see their unpaid bills</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedVendor && filteredBills.length === 0">No unpaid bills for this vendor</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedVendor && filteredBills.length > 0">Leave empty for general payment</p>
                        </div>

                        <!-- Amount -->
                        <div>
                            <label for="amount" class="form-label">Amount <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" value="{{ old('amount', $bill?->balance_due) }}" required placeholder="0.00"
                                    class="form-control @error('amount') border-red-500 @enderror" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                            </div>
                            @error('amount')
                                <p id="amount-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Payment Method -->
                        <div x-data="{ paymentMethod: '{{ old('payment_method', '') }}' }">
                            <label for="payment_method" class="form-label">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" id="payment_method" required x-model="paymentMethod"
                                class="form-control @error('payment_method') border-red-500 @enderror" @error('payment_method') aria-invalid="true" aria-describedby="payment_method-error" @enderror>
                                <option value="">Select Method</option>
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
                                        <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                                            {{ $bank->name }} ({{ $bank->account_number }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select which bank account to pay from</p>
                                @error('bank_id')
                                    <p id="bank_id-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Reference -->
                        <div class="md:col-span-2">
                            <x-field name="reference" label="Reference Number" :value="old('reference')" placeholder="e.g., Check #, Transaction ID" />
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                class="form-control"
                                placeholder="Optional notes about this payment">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('payments-made.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Record Payment
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

        @php
            $unpaidBills = \App\Models\Bill::whereIn('status', ['unpaid', 'partial'])->get(['id', 'vendor_id', 'bill_number', 'balance_due']);
        @endphp
        function paymentForm() {
            return {
                selectedVendor: '{{ old('vendor_id', $bill?->vendor_id ?? '') }}',
                selectedBill: '{{ old('bill_id', $bill?->id ?? '') }}',
                allBills: @json($unpaidBills),
                filteredBills: [],

                init() {
                    this.filterBills();
                },

                filterBills() {
                    if (this.selectedVendor) {
                        this.filteredBills = this.allBills.filter(b => b.vendor_id == this.selectedVendor);
                    } else {
                        this.filteredBills = [];
                    }
                    // Reset selected bill if not in filtered list
                    if (this.selectedBill && !this.filteredBills.find(b => b.id == this.selectedBill)) {
                        this.selectedBill = '';
                    }
                },

                updateAmount() {
                    if (this.selectedBill) {
                        const bill = this.filteredBills.find(b => b.id == this.selectedBill);
                        if (bill) {
                            document.getElementById('amount').value = parseFloat(bill.balance_due).toFixed(2);
                        }
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
