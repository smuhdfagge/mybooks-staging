<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Sales Order') }} #{{ $salesOrder->order_number }}
            </h2>
            <a href="{{ route('sales-orders.show', $salesOrder) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Order
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('sales-orders.update', $salesOrder) }}" method="POST" x-data="salesOrderForm()" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Order Header -->
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Order Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div>
                                <label for="order_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Order Number</label>
                                <input type="text" id="order_number" value="{{ $salesOrder->order_number }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div x-data="searchableSelect({
                                items: {{ json_encode($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->name . ($c->company_name ? " ({$c->company_name})" : '')])) }},
                                selected: '{{ old('customer_id', $salesOrder->customer_id) }}',
                                placeholder: 'Select Customer'
                            })">
                                <label for="customer_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Customer <span class="text-red-500">*</span></label>
                                <input type="hidden" name="customer_id" :value="selectedId" required>
                                <div class="relative">
                                    <input type="text" 
                                        x-model="search" 
                                        @click="open = true" 
                                        @keydown.arrow-down.prevent="highlightedIndex = Math.min(highlightedIndex + 1, filteredItems.length - 1)"
                                        @keydown.arrow-up.prevent="highlightedIndex = Math.max(highlightedIndex - 1, 0)"
                                        @keydown.enter.prevent="if(filteredItems[highlightedIndex]) selectItem(filteredItems[highlightedIndex])"
                                        @keydown.escape="open = false"
                                        placeholder="Select Customer"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('customer_id') border-red-500 @enderror">
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
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="order_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Order Date <span class="text-red-500">*</span></label>
                                <input type="date" name="order_date" id="order_date" value="{{ old('order_date', $salesOrder->order_date->format('Y-m-d')) }}" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('order_date') border-red-500 @enderror">
                                @error('order_date')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="expected_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expected Delivery Date</label>
                                <input type="date" name="expected_date" id="expected_date" value="{{ old('expected_date', $salesOrder->expected_date?->format('Y-m-d')) }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('expected_date') border-red-500 @enderror">
                                @error('expected_date')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference / PO Number</label>
                            <input type="text" name="reference" id="reference" value="{{ old('reference', $salesOrder->reference) }}"
                                class="w-full md:w-1/2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            Order Items
                        </h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
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
                                            <td class="py-2 pr-2">
                                                <div class="relative mb-1">
                                                    <input type="hidden" :name="`items[${index}][item_id]`" x-model="item.item_id">
                                                    <input type="text" 
                                                        x-model="item.itemSearch" 
                                                        @click="item.itemDropdownOpen = true" 
                                                        @keydown.arrow-down.prevent="item.itemHighlightedIndex = Math.min(item.itemHighlightedIndex + 1, getFilteredProducts(index).length - 1)"
                                                        @keydown.arrow-up.prevent="item.itemHighlightedIndex = Math.max(item.itemHighlightedIndex - 1, 0)"
                                                        @keydown.enter.prevent="if(getFilteredProducts(index)[item.itemHighlightedIndex]) selectProduct(index, getFilteredProducts(index)[item.itemHighlightedIndex])"
                                                        @keydown.escape="item.itemDropdownOpen = false"
                                                        placeholder="Select Item"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
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
                                                <input type="text" :name="`items[${index}][description]`" x-model="item.description" required placeholder="Description"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:placeholder-gray-500 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="number" :name="`items[${index}][quantity]`" x-model.number="item.quantity" min="0.01" step="0.01" required
                                                    @input="calculateTotals()"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="number" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" min="0" step="0.01" required
                                                    @input="calculateTotals()"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </td>
                                            <td class="py-2 pr-2">
                                                {{-- Discounts set elsewhere (API, quotation) are kept. --}}
                                                <input type="hidden" :name="`items[${index}][discount]`" :value="item.discount || 0">
                                                <input type="number" :name="`items[${index}][tax_rate]`" x-model.number="item.tax_rate" min="0" max="100" step="0.01"
                                                    @input="calculateTotals()"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </td>
                                            <td class="py-2 text-right text-sm font-medium text-gray-900 dark:text-gray-100" x-text="'₦' + lineTotal(index).toFixed(2)"></td>
                                            <td class="py-2 text-center">
                                                <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
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
                                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Internal Notes</label>
                                <textarea name="notes" id="notes" rows="4"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $salesOrder->notes) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                                Order Summary
                            </h3>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="'₦' + subtotal.toFixed(2)">₦0.00</span>
                                </div>

                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="'₦' + totalTax.toFixed(2)">₦0.00</span>
                                </div>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400" x-text="'₦' + total.toFixed(2)">₦0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('sales-orders.show', $salesOrder) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Update Sales Order
                    </button>
                </div>
            </form>
        </div>
    </div>

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

        function salesOrderForm() {
            return {
                availableProducts: @js($items->map(fn($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'price' => $i->selling_price,
                    'description' => $i->description,
                    'tax_rate' => $i->tax_rate ?? 0
                ])),
                items: @js($salesOrder->items->map(fn($item) => [
                    'item_id' => $item->item_id ?? '',
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => (float) $item->discount,
                    'tax_rate' => $item->tax_rate,
                    'itemSearch' => '',
                    'itemDropdownOpen' => false,
                    'itemHighlightedIndex' => 0
                ])),
                subtotal: {{ $salesOrder->subtotal }},
                totalTax: {{ $salesOrder->tax_amount }},
                total: {{ $salesOrder->total }},

                init() {
                    this.items.forEach((item, index) => {
                        if (item.item_id) {
                            const product = this.availableProducts.find(p => p.id == item.item_id);
                            if (product) {
                                item.itemSearch = product.name;
                            }
                        }
                    });
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
                    this.items[index].tax_rate = product.tax_rate;
                    this.items[index].itemDropdownOpen = false;
                    this.items[index].itemHighlightedIndex = 0;
                    this.calculateTotals();
                },

                addItem() {
                    this.items.push({ item_id: '', description: '', quantity: 1, unit_price: 0, tax_rate: 0, itemSearch: '', itemDropdownOpen: false, itemHighlightedIndex: 0 });
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
                        this.items[index].tax_rate = parseFloat(option.dataset.tax) || 0;
                        this.calculateTotals();
                    }
                },

                lineTotal(index) {
                    const item = this.items[index];
                    const lineSubtotal = (item.quantity || 0) * (item.unit_price || 0);
                    const lineTax = lineSubtotal * ((item.tax_rate || 0) / 100);
                    return lineSubtotal + lineTax;
                },

                calculateTotals() {
                    this.subtotal = this.items.reduce((sum, item) => {
                        return sum + ((item.quantity || 0) * (item.unit_price || 0));
                    }, 0);

                    this.totalTax = this.items.reduce((sum, item) => {
                        const lineSubtotal = (item.quantity || 0) * (item.unit_price || 0);
                        return sum + (lineSubtotal * ((item.tax_rate || 0) / 100));
                    }, 0);

                    this.total = this.subtotal + this.totalTax;
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
