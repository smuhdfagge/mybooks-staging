<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Invoice') }}: {{ $invoice->invoice_number }}
            </h2>
            <a href="{{ route('invoices.show', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Cancel
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('invoices.update', $invoice) }}" method="POST" x-data="invoiceForm()" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Invoice Header -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div>
                                <label for="invoice_number" class="form-label">Invoice Number</label>
                                <input type="text" id="invoice_number" value="{{ $invoice->invoice_number }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 shadow-sm">
                            </div>

                            <div x-data="searchableSelect({
                                items: {{ json_encode($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->name . ($c->company_name ? " ({$c->company_name})" : '')])) }},
                                selected: '{{ old('customer_id', $invoice->customer_id) }}',
                                placeholder: 'Select Customer'
                            })">
                                <label for="customer_id" class="form-label">Customer <span class="text-red-500">*</span></label>
                                <input type="hidden" name="customer_id" :value="selectedId" required @error('customer_id') aria-invalid="true" aria-describedby="customer_id-error" @enderror>
                                <div class="relative">
                                    <input type="text" 
                                        x-model="search" 
                                        @click="open = true" 
                                        @keydown.arrow-down.prevent="highlightedIndex = Math.min(highlightedIndex + 1, filteredItems.length - 1)"
                                        @keydown.arrow-up.prevent="highlightedIndex = Math.max(highlightedIndex - 1, 0)"
                                        @keydown.enter.prevent="if(filteredItems[highlightedIndex]) selectItem(filteredItems[highlightedIndex])"
                                        @keydown.escape="open = false"
                                        placeholder="Select Customer"
                                        class="form-control @error('customer_id') border-red-500 @enderror">
                                    <div x-show="open" 
                                        @click.away="open = false"
                                        class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                                        <template x-for="(item, index) in filteredItems" :key="item.id">
                                            <div @click="selectItem(item)"
                                                :class="{'bg-indigo-50 dark:bg-indigo-900': index === highlightedIndex}"
                                                class="px-3 py-2 cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900 text-gray-900 dark:text-gray-100"
                                                x-text="item.name"></div>
                                        </template>
                                        <div x-show="filteredItems.length === 0" class="px-3 py-2 text-gray-500 dark:text-gray-400">No results found</div>
                                    </div>
                                </div>
                                @error('customer_id')
                                    <p id="customer_id-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="invoice_date" class="form-label">Invoice Date <span class="text-red-500">*</span></label>
                                <input type="date" name="invoice_date" id="invoice_date" value="{{ old('invoice_date', $invoice->invoice_date->format('Y-m-d')) }}" required
                                    class="form-control @error('invoice_date') border-red-500 @enderror" @error('invoice_date') aria-invalid="true" aria-describedby="invoice_date-error" @enderror>
                                @error('invoice_date')
                                    <p id="invoice_date-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="due_date" class="form-label">Due Date <span class="text-red-500">*</span></label>
                                <input type="date" name="due_date" id="due_date" value="{{ old('due_date', $invoice->due_date->format('Y-m-d')) }}" required
                                    class="form-control @error('due_date') border-red-500 @enderror" @error('due_date') aria-invalid="true" aria-describedby="due_date-error" @enderror>
                                @error('due_date')
                                    <p id="due_date-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="reference" class="form-label">Reference / PO Number</label>
                                <input type="text" name="reference" id="reference" value="{{ old('reference', $invoice->reference) }}"
                                    class="form-control">
                            </div>
                            <div>
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status"
                                    class="form-control">
                                    <option value="draft" {{ old('status', $invoice->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="sent" {{ old('status', $invoice->status) == 'sent' ? 'selected' : '' }}>Sent</option>
                                    <option value="viewed" {{ old('status', $invoice->status) == 'viewed' ? 'selected' : '' }}>Viewed</option>
                                    <option value="partial" {{ old('status', $invoice->status) == 'partial' ? 'selected' : '' }}>Partial</option>
                                    <option value="paid" {{ old('status', $invoice->status) == 'paid' ? 'selected' : '' }}>Paid</option>
                                    <option value="overdue" {{ old('status', $invoice->status) == 'overdue' ? 'selected' : '' }}>Overdue</option>
                                    <option value="cancelled" {{ old('status', $invoice->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Invoice Items</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full line-items">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-1/3">Description</th>
                                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-24">Qty</th>
                                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Price</th>
                                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-24">Tax %</th>
                                        <th class="text-right text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Total</th>
                                        <th class="w-12"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in items" :key="index">
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <td class="py-2 pr-2" data-label="Description" data-cell="main">
                                                <div class="relative">
                                                    <input type="hidden" :name="`items[${index}][item_id]`" x-model="item.item_id">
                                                    <input aria-label="Select Item" type="text" 
                                                        x-model="item.itemSearch" 
                                                        @click="item.itemDropdownOpen = true" 
                                                        @keydown.arrow-down.prevent="item.itemHighlightedIndex = Math.min(item.itemHighlightedIndex + 1, getFilteredProducts(index).length - 1)"
                                                        @keydown.arrow-up.prevent="item.itemHighlightedIndex = Math.max(item.itemHighlightedIndex - 1, 0)"
                                                        @keydown.enter.prevent="if(getFilteredProducts(index)[item.itemHighlightedIndex]) selectProduct(index, getFilteredProducts(index)[item.itemHighlightedIndex])"
                                                        @keydown.escape="item.itemDropdownOpen = false"
                                                        placeholder="Select Item"
                                                        class="form-control text-sm mb-1">
                                                    <div x-show="item.itemDropdownOpen" 
                                                        @click.away="item.itemDropdownOpen = false"
                                                        class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                                                        <template x-for="(product, pIndex) in getFilteredProducts(index)" :key="product.id">
                                                            <div @click="selectProduct(index, product)"
                                                                :class="{'bg-indigo-50 dark:bg-indigo-900': pIndex === item.itemHighlightedIndex}"
                                                                class="px-3 py-2 cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900 text-gray-900 dark:text-gray-100 text-sm"
                                                                x-text="product.name"></div>
                                                        </template>
                                                        <div x-show="getFilteredProducts(index).length === 0" class="px-3 py-2 text-gray-500 dark:text-gray-400 text-sm">No results found</div>
                                                    </div>
                                                </div>
                                                <input aria-label="Description" type="text" :name="`items[${index}][description]`" x-model="item.description" required placeholder="Description"
                                                    class="form-control text-sm">
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
                                            <td class="py-2 pr-2" data-label="Tax %">
                                                <input aria-label="Tax rate (%)" type="number" :name="`items[${index}][tax_rate]`" x-model.number="item.tax_rate" min="0" max="100" step="0.01"
                                                    @input="calculateTotals()"
                                                    class="form-control text-sm">
                                            </td>
                                            <td class="py-2 text-right text-sm font-medium text-gray-900 dark:text-gray-100" x-text="formatMoney(lineTotal(index))" data-label="Total"></td>
                                            <td class="py-2 text-center" data-cell="actions">
                                                <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" aria-label="Remove line">
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
                    <!-- Notes & Terms -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="mb-4">
                                <label for="notes" class="form-label">Notes (visible on invoice)</label>
                                <textarea name="notes" id="notes" rows="3"
                                    class="form-control">{{ old('notes', $invoice->notes) }}</textarea>
                            </div>
                            <div>
                                <label for="terms" class="form-label">Terms & Conditions</label>
                                <textarea name="terms" id="terms" rows="3"
                                    class="form-control">{{ old('terms', $invoice->terms) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Invoice Summary</h3>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="formatMoney(subtotal)">@money(0)</span>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-600 dark:text-gray-400">Discount</span>
                                        <select name="discount_type" x-model="discountType" @change="calculateTotals()"
                                            class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1">
                                            <option value="">None</option>
                                            <option value="percentage">%</option>
                                            <option value="fixed">@currencySymbol</option>
                                        </select>
                                        <input type="number" name="discount_amount" x-model.number="discountValue" x-show="discountType" min="0" step="0.01"
                                            @input="calculateTotals()"
                                            class="w-20 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1">
                                    </div>
                                    <span class="font-medium text-red-600 dark:text-red-400" x-text="'-' + formatMoney(discount)">-@money(0)</span>
                                </div>

                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="formatMoney(totalTax)">@money(0)</span>
                                </div>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400" x-text="formatMoney(total)">@money(0)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('invoices.show', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Update Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>

    @php
        $availableProductsData = $items->map(fn($i) => [
            'id' => $i->id,
            'name' => $i->name . ($i->is_taxable ? ' (Taxable)' : ''),
            'price' => $i->selling_price,
            'description' => $i->description,
            'taxable' => $i->is_taxable,
            'tax_rate' => $i->effective_tax_rate ?? 0,
        ]);
        $invoiceItemsData = $invoice->items->map(fn($i) => [
            'item_id' => $i->item_id ?? '',
            'description' => $i->description,
            'quantity' => $i->quantity,
            'unit_price' => $i->unit_price,
            'tax_rate' => $i->tax_rate ?? 0,
            'is_taxable' => ($i->tax_rate ?? 0) > 0,
            'itemSearch' => '',
            'itemDropdownOpen' => false,
            'itemHighlightedIndex' => 0,
        ]);
    @endphp

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function searchableSelect(config) {
            return {
                items: config.items || [],
                selected: config.selected || '',
                selectedId: config.selected || '',
                search: '',
                open: false,
                highlightedIndex: 0,
                placeholder: config.placeholder || 'Select an option',
                init() {
                    if (this.selected) {
                        const selectedItem = this.items.find(item => item.id == this.selected);
                        if (selectedItem) {
                            this.search = selectedItem.name;
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
                    this.highlightedIndex = 0;
                }
            };
        }

        function invoiceForm() {
            return {
                availableProducts: @json($availableProductsData),
                items: @json($invoiceItemsData),
                discountType: '{{ $invoice->discount_type ?? '' }}',
                discountValue: {{ $invoice->discount_type === 'percentage' ? ($invoice->subtotal > 0 ? ($invoice->discount_amount / $invoice->subtotal * 100) : 0) : ($invoice->discount_amount ?? 0) }},
                subtotal: 0,
                totalTax: 0,
                discount: 0,
                total: 0,

                init() {
                    this.items.forEach((item, index) => {
                        if (item.item_id) {
                            const product = this.availableProducts.find(p => p.id == item.item_id);
                            if (product) {
                                item.itemSearch = product.name;
                            }
                        }
                    });
                    this.calculateTotals();
                },

                getFilteredProducts(index) {
                    const search = this.items[index].itemSearch || '';
                    if (!search) return this.availableProducts;
                    return this.availableProducts.filter(product => 
                        product.name.toLowerCase().includes(search.toLowerCase())
                    );
                },

                selectProduct(index, product) {
                    this.items[index].item_id = product.id;
                    this.items[index].itemSearch = product.name;
                    this.items[index].unit_price = product.price;
                    this.items[index].description = product.description || product.name;
                    this.items[index].is_taxable = product.taxable;
                    this.items[index].tax_rate = product.taxable ? product.tax_rate : 0;
                    this.items[index].itemDropdownOpen = false;
                    this.items[index].itemHighlightedIndex = 0;
                    this.calculateTotals();
                },

                addItem() {
                    this.items.push({ item_id: '', description: '', quantity: 1, unit_price: 0, tax_rate: 0, is_taxable: false, itemSearch: '', itemDropdownOpen: false, itemHighlightedIndex: 0 });
                },

                removeItem(index) {
                    this.items.splice(index, 1);
                    this.calculateTotals();
                },

                fillItem(index) {
                    const select = document.querySelector(`select[name="items[${index}][item_id]"]`);
                    const option = select.options[select.selectedIndex];
                    if (option && option.value) {
                        this.items[index].unit_price = parseFloat(option.dataset.price) || 0;
                        this.items[index].description = option.dataset.desc || option.text;
                        // Only apply tax if item is taxable
                        const isTaxable = option.dataset.taxable === '1';
                        this.items[index].is_taxable = isTaxable;
                        this.items[index].tax_rate = isTaxable ? (parseFloat(option.dataset.tax) || 0) : 0;
                        this.calculateTotals();
                    }
                },

                // Share of the invoice discount left after it is applied: VAT is
                // charged on the discounted amount, as the server does (A4).
                discountFactor() {
                    return this.subtotal > 0 ? Math.max(0, 1 - (this.discount || 0) / this.subtotal) : 1;
                },

                lineTotal(index) {
                    const item = this.items[index];
                    const lineSubtotal = (item.quantity || 0) * (item.unit_price || 0);
                    const lineTax = lineSubtotal * this.discountFactor() * ((item.tax_rate || 0) / 100);
                    return lineSubtotal + lineTax;
                },

                calculateTotals() {
                    this.subtotal = this.items.reduce((sum, item) => {
                        return sum + ((item.quantity || 0) * (item.unit_price || 0));
                    }, 0);

                    if (this.discountType === 'percentage') {
                        this.discount = this.subtotal * ((this.discountValue || 0) / 100);
                    } else if (this.discountType === 'fixed') {
                        this.discount = Math.min(this.discountValue || 0, this.subtotal);
                    } else {
                        this.discount = 0;
                    }

                    const factor = this.discountFactor();
                    this.totalTax = this.items.reduce((sum, item) => {
                        const lineSubtotal = (item.quantity || 0) * (item.unit_price || 0);
                        return sum + (lineSubtotal * factor * ((item.tax_rate || 0) / 100));
                    }, 0);

                    this.total = this.subtotal + this.totalTax - this.discount;
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
