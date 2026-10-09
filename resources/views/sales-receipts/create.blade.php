<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create Sales Receipt') }}
            </h2>
            <a href="{{ route('sales-receipts.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <x-form-auto-save formKey="sales-receipt-create">
            <form action="{{ route('sales-receipts.store') }}" method="POST" x-data="salesReceiptForm()" class="space-y-6">
                @csrf
                <x-lock-date-notice field="receipt_date" />

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Receipt Header -->
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Receipt Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div>
                                <label for="receipt_number" class="form-label">Receipt Number</label>
                                <input type="text" id="receipt_number" value="{{ $receiptNumber }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div x-data="searchableSelect({
                                items: @js($customers->map(fn ($customer) => ['id' => (string) $customer->id, 'name' => $customer->name . ($customer->company_name ? " (" . ($customer->company_name) . ")" : "")])->values()),
                                selectedId: '{{ old('customer_id') }}'
                            })" class="relative">
                                <label for="customer_search" class="form-label">Customer</label>
                                <input type="hidden" name="customer_id" :value="selectedId" @error('customer_id') aria-invalid="true" aria-describedby="customer_id-error" @enderror>
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
                                        placeholder="Walk-in Customer or search..."
                                        autocomplete="off"
                                        class="form-control @error('customer_id') border-red-500 @enderror">
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
                                            :class="{ 'bg-brand-600 text-white': highlightedIndex === index, 'text-gray-900 dark:text-gray-100': highlightedIndex !== index }"
                                            class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-brand-600 hover:text-white">
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
                                    <p id="customer_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-field name="receipt_date" label="Receipt Date" type="date" :value="old('receipt_date', date('Y-m-d'))" required />
                            </div>

                            <div>
                                <label for="payment_method" class="form-label">Payment Method <span class="text-red-600 dark:text-red-300">*</span></label>
                                <select name="payment_method" id="payment_method" required
                                    class="form-control @error('payment_method') border-red-500 @enderror" @error('payment_method') aria-invalid="true" aria-describedby="payment_method-error" @enderror>
                                    <option value="">Select Method</option>
                                    <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="check" {{ old('payment_method') == 'check' ? 'selected' : '' }}>Check</option>
                                    <option value="credit_card" {{ old('payment_method') == 'credit_card' ? 'selected' : '' }}>Credit Card</option>
                                    <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="other" {{ old('payment_method') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('payment_method')
                                    <p id="payment_method-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <x-warehouse-picker wrapper-class="mb-6 md:w-1/2" label="Sell from warehouse" />

                        <div class="mb-6">
                            <label for="reference" class="form-label">Reference</label>
                            <input type="text" name="reference" id="reference" value="{{ old('reference') }}"
                                class="w-full md:w-1/2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                </div>

                <!-- Receipt Items -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            Receipt Items
                        </h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full line-items">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-1/3">Description</th>
                                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-24">Qty</th>
                                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Price</th>
                                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-24">VAT %</th>
                                        <th class="text-right text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Total</th>
                                        <th class="w-12"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in items" :key="index">
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <td class="py-2 pr-2" data-label="Description" data-cell="main">
                                                <div class="relative mb-1">
                                                    <input type="hidden" :name="`items[${index}][item_id]`" x-model="item.item_id">
                                                    <input aria-label="Item" 
                                                        type="text" 
                                                        x-model="item.itemSearch"
                                                        @focus="item.itemDropdownOpen = true"
                                                        @click="item.itemDropdownOpen = true"
                                                        @input="item.itemDropdownOpen = true"
                                                        @keydown.escape="item.itemDropdownOpen = false"
                                                        @keydown.arrow-down.prevent="item.itemHighlightedIndex = Math.min(item.itemHighlightedIndex + 1, getFilteredProducts(item.itemSearch).length - 1)"
                                                        @keydown.arrow-up.prevent="item.itemHighlightedIndex = Math.max(item.itemHighlightedIndex - 1, 0)"
                                                        @keydown.enter.prevent="selectProduct(index, getFilteredProducts(item.itemSearch)[item.itemHighlightedIndex])"
                                                        placeholder="Search items..."
                                                        autocomplete="off"
                                                        class="form-control text-sm">
                                                    <button type="button" aria-label="Show items" @click="item.itemDropdownOpen = !item.itemDropdownOpen" class="absolute inset-y-0 right-0 flex items-center pr-2">
                                                        <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                    <div 
                                                        x-show="item.itemDropdownOpen" 
                                                        @click.away="item.itemDropdownOpen = false"
                                                        x-transition
                                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-48 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none text-sm">
                                                        <template x-for="(product, pIndex) in getFilteredProducts(item.itemSearch)" :key="product.id">
                                                            <div 
                                                                @click="selectProduct(index, product)"
                                                                @mouseenter="item.itemHighlightedIndex = pIndex"
                                                                :class="{ 'bg-brand-600 text-white': item.itemHighlightedIndex === pIndex, 'text-gray-900 dark:text-gray-100': item.itemHighlightedIndex !== pIndex }"
                                                                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-brand-600 hover:text-white">
                                                                <span x-text="product.name" class="block truncate"></span>
                                                                <span x-show="item.item_id == product.id" class="absolute inset-y-0 right-0 flex items-center pr-4">
                                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </span>
                                                            </div>
                                                        </template>
                                                        <div x-show="getFilteredProducts(item.itemSearch).length === 0" class="py-2 px-3 text-gray-500 dark:text-gray-400 text-sm">
                                                            No items found
                                                        </div>
                                                    </div>
                                                </div>
                                                <input aria-label="Description" type="text" :name="`items[${index}][description]`" x-model="item.description" required placeholder="Description"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:placeholder-gray-500 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                                            </td>
                                            <td class="py-2 pr-2" data-label="Qty">
                                                <input aria-label="Quantity" type="number" :name="`items[${index}][quantity]`" x-model.number="item.quantity" min="0.01" step="0.01" required
                                                    @input="calculateTotals()"
                                                    class="form-control text-sm">
                                            </td>
                                            <td class="py-2 pr-2" data-label="Price">
                                                <input aria-label="Unit price" type="number" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" min="0" step="0.01" required
                                                    @input="calculateTotals()"
                                                    class="form-control text-sm">
                                            </td>
                                            <td class="py-2 pr-2" data-label="VAT %">
                                                {{-- VAT on cash sales, as on invoices (R3). Line discounts set elsewhere are kept. --}}
                                                <input type="hidden" :name="`items[${index}][discount]`" :value="item.discount || 0">
                                                <input aria-label="VAT rate (%)" type="number" :name="`items[${index}][tax_rate]`" x-model.number="item.tax_rate" min="0" max="100" step="0.01"
                                                    @input="calculateTotals()"
                                                    class="form-control text-sm">
                                            </td>
                                            <td class="py-2 text-right text-sm font-medium text-gray-900 dark:text-gray-100" x-text="formatMoney(lineTotal(index))" data-label="Total"></td>
                                            <td class="py-2 text-center" data-cell="actions">
                                                <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-red-600 hover:text-red-800 dark:text-red-300 dark:hover:text-red-300" aria-label="Remove line">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" @click="addItem()" class="mt-4 inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Line Item
                        </button>
                    </div>
                </div>

                <!-- Totals & Notes -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Notes -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Notes
                            </h3>
                            <div>
                                <x-field name="notes" label="Internal Notes" type="textarea" :value="old('notes')" rows="4" />
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                Receipt Summary
                            </h3>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">VAT</span>
                                    <span class="text-gray-900 dark:text-gray-100" x-text="formatMoney(totalTax)">@money(0)</span>
                                </div>
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-lg font-bold text-brand-600 dark:text-brand-300" x-text="formatMoney(total)">@money(0)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('sales-receipts.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Create Sales Receipt
                    </button>
                </div>
            </form>
            </x-form-auto-save>
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

        function salesReceiptForm() {
            return {
                items: [{ item_id: '', description: '', quantity: 1, unit_price: 0, tax_rate: 0, discount: 0, itemSearch: '', itemDropdownOpen: false, itemHighlightedIndex: 0 }],
                availableProducts: @js($items->map(fn ($item) => ['id' => (string) $item->id, 'name' => (string) $item->name, 'price' => (float) $item->selling_price, 'desc' => (string) ($item->description ?? $item->name), 'tax' => (float) ($item->is_taxable ? ($item->effective_tax_rate ?? 0) : 0)])->values()),
                total: 0,
                totalTax: 0,

                addItem() {
                    this.items.push({ item_id: '', description: '', quantity: 1, unit_price: 0, tax_rate: 0, discount: 0, itemSearch: '', itemDropdownOpen: false, itemHighlightedIndex: 0 });
                },

                removeItem(index) {
                    this.items.splice(index, 1);
                    this.calculateTotals();
                },

                getFilteredProducts(search) {
                    if (!search) return this.availableProducts;
                    return this.availableProducts.filter(p => 
                        p.name.toLowerCase().includes(search.toLowerCase())
                    );
                },

                selectProduct(index, product) {
                    if (!product) return;
                    this.items[index].item_id = product.id;
                    this.items[index].itemSearch = product.name;
                    this.items[index].unit_price = product.price;
                    this.items[index].tax_rate = product.tax || 0;
                    this.items[index].description = product.desc || product.name;
                    this.items[index].itemDropdownOpen = false;
                    this.calculateTotals();
                },

                lineNet(item) {
                    return Math.max(0, (item.quantity || 0) * (item.unit_price || 0) - (item.discount || 0));
                },

                lineTotal(index) {
                    const item = this.items[index];
                    return this.lineNet(item) * (1 + (item.tax_rate || 0) / 100);
                },

                calculateTotals() {
                    this.totalTax = this.items.reduce((sum, item) => sum + this.lineNet(item) * ((item.tax_rate || 0) / 100), 0);
                    this.total = this.items.reduce((sum, item) => sum + this.lineNet(item), 0) + this.totalTax;
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
