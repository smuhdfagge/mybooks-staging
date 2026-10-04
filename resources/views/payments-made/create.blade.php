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
                    <x-lock-date-notice field="payment_date" />

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
                            onSelect: (id) => { selectedVendor = id; filterBills(); whtCategoryId = ''; deductWht = !!(vendors[id] && vendors[id].wht_category_id && !vendors[id].wht_exempt); recalculate(); }
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
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" x-model="amount" @input="amountTyped()" required placeholder="0.00"
                                    class="form-control @error('amount') border-red-500 @enderror" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="deductWht">Money paid from the bank, after WHT.</p>
                            @error('amount')
                                <p id="amount-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Withholding tax -->
                        <div class="md:col-span-2 rounded-md border border-gray-200 dark:border-gray-700 p-4">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <input type="checkbox" x-model="deductWht" @change="recalculate()" :disabled="vendorExempt()">
                                Deduct withholding tax (WHT)
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="vendorExempt()">This vendor is marked as exempt from WHT.</p>
                            <div x-show="deductWht" x-cloak class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="wht_category_id" class="form-label">WHT transaction type</label>
                                    <select name="wht_category_id" id="wht_category_id" x-model="whtCategoryId" @change="recalculate()" :disabled="!deductWht"
                                        class="form-control @error('wht_category_id') border-red-500 @enderror">
                                        <option value="">Select type</option>
                                        <template x-for="c in categories" :key="c.id">
                                            <option :value="c.id" x-text="c.name"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="whtCategoryId" x-text="'Rate ' + rate() + '% on the amount before VAT' + (vendorHasTin() ? '' : ' (doubled: vendor has no TIN)')"></p>
                                    @error('wht_category_id')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="wht_amount" class="form-label">WHT withheld</label>
                                    <input type="number" name="wht_amount" id="wht_amount" step="0.01" min="0" x-model="whtAmount" :disabled="!deductWht"
                                        class="form-control @error('wht_amount') border-red-500 @enderror">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedBill">
                                        Settles <span x-text="formatMoney((parseFloat(amount) || 0) + (parseFloat(whtAmount) || 0))"></span> of the bill.
                                    </p>
                                    @error('wht_amount')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
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
            $unpaidBills = \App\Models\Bill::whereIn('status', ['unpaid', 'partial'])->get(['id', 'vendor_id', 'bill_number', 'balance_due', 'total', 'tax_amount']);
            $whtVendors = $vendors->mapWithKeys(fn ($v) => [$v->id => [
                'payee_type' => $v->payee_type ?? 'company', 'has_tin' => $v->hasTin(),
                'wht_category_id' => $v->wht_category_id, 'wht_exempt' => (bool) $v->wht_exempt,
            ]]);
            $whtCategories = \App\Models\WhtCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'rate_company', 'rate_individual', 'double_without_tin']);
        @endphp
        function paymentForm() {
            return {
                selectedVendor: '{{ old('vendor_id', $bill?->vendor_id ?? '') }}',
                selectedBill: '{{ old('bill_id', $bill?->id ?? '') }}',
                allBills: @json($unpaidBills),
                filteredBills: [],
                amount: '{{ old('amount', $bill?->balance_due) }}',
                // Withholding tax (worked out on the amount before VAT).
                vendors: @json($whtVendors),
                categories: @json($whtCategories),
                deductWht: {{ old('wht_category_id') || old('wht_amount') ? 'true' : 'false' }},
                whtCategoryId: '{{ old('wht_category_id') }}',
                whtAmount: '{{ old('wht_amount') }}',

                init() {
                    this.filterBills();
                    // Paying a bill of a vendor with a usual WHT type: suggest it.
                    if (!this.deductWht && this.selectedBill && this.vendor() && this.vendor().wht_category_id && !this.vendor().wht_exempt) {
                        this.deductWht = true;
                        this.recalculate();
                    }
                },

                vendor() {
                    return this.vendors[this.selectedVendor] || null;
                },

                vendorExempt() {
                    return !!(this.vendor() && this.vendor().wht_exempt);
                },

                vendorHasTin() {
                    return !this.vendor() || this.vendor().has_tin;
                },

                rate() {
                    const c = this.categories.find(c => c.id == this.whtCategoryId);
                    if (!c) return 0;
                    const v = this.vendor();
                    let r = parseFloat(v && v.payee_type === 'individual' ? c.rate_individual : c.rate_company) || 0;
                    if (v && !v.has_tin && c.double_without_tin) r *= 2;
                    return Math.round(r * 100) / 100;
                },

                bill() {
                    return this.filteredBills.find(b => b.id == this.selectedBill) || null;
                },

                exVatShare() {
                    const b = this.bill();
                    const total = b ? parseFloat(b.total) : 0;
                    return total > 0 ? Math.min(1, Math.max(0, (total - (parseFloat(b.tax_amount) || 0)) / total)) : 1;
                },

                // With a bill: settle what is owed, net = owed - WHT.
                // Without one: the amount typed is the net; WHT is grossed up.
                recalculate() {
                    if (this.deductWht && !this.whtCategoryId && this.vendor() && this.vendor().wht_category_id) {
                        this.whtCategoryId = String(this.vendor().wht_category_id);
                    }
                    const b = this.bill();
                    if (!this.deductWht || !this.whtCategoryId) {
                        this.whtAmount = '';
                        if (b) this.amount = parseFloat(b.balance_due).toFixed(2);
                        return;
                    }
                    const r = this.rate() / 100 * this.exVatShare();
                    if (b) {
                        const owed = parseFloat(b.balance_due);
                        const wht = Math.round(owed * r * 100) / 100;
                        this.whtAmount = wht.toFixed(2);
                        this.amount = (owed - wht).toFixed(2);
                    } else {
                        const net = parseFloat(this.amount) || 0;
                        this.whtAmount = r > 0 && r < 1 ? (Math.round(r * net / (1 - r) * 100) / 100).toFixed(2) : '0.00';
                    }
                },

                amountTyped() {
                    if (this.deductWht && !this.bill()) this.recalculate();
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
                    const bill = this.bill();
                    if (bill) {
                        this.amount = parseFloat(bill.balance_due).toFixed(2);
                    }
                    this.recalculate();
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
