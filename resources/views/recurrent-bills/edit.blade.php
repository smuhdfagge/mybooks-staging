<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Recurrent Bill Profile') }} - {{ $recurrentBill->profile_name }}
            </h2>
            <a href="{{ route('recurrent-bills.show', $recurrentBill) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Details
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('recurrent-bills.update', $recurrentBill) }}" method="POST" x-data="recurrentBillForm()" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Profile Header -->
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Profile Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            <div>
                                <label for="profile_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Profile Name <span class="text-red-500">*</span></label>
                                <input type="text" name="profile_name" id="profile_name" value="{{ old('profile_name', $recurrentBill->profile_name) }}" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('profile_name') border-red-500 @enderror">
                                @error('profile_name')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="vendor" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vendor</label>
                                <input type="text" id="vendor" value="{{ $recurrentBill->vendor->name ?? 'Unknown' }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div>
                                <label for="frequency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Frequency <span class="text-red-500">*</span></label>
                                <select name="frequency" id="frequency" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('frequency') border-red-500 @enderror">
                                    <option value="weekly" {{ old('frequency', $recurrentBill->frequency) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="monthly" {{ old('frequency', $recurrentBill->frequency) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="quarterly" {{ old('frequency', $recurrentBill->frequency) == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                    <option value="yearly" {{ old('frequency', $recurrentBill->frequency) == 'yearly' ? 'selected' : '' }}>Yearly</option>
                                </select>
                                @error('frequency')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                                <input type="text" id="start_date" value="{{ $recurrentBill->start_date->format('M d, Y') }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                                <input type="date" name="end_date" id="end_date" value="{{ old('end_date', $recurrentBill->end_date?->format('Y-m-d')) }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('end_date') border-red-500 @enderror">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty for indefinite</p>
                                @error('end_date')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select name="status" id="status"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="active" {{ old('status', $recurrentBill->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="paused" {{ old('status', $recurrentBill->status) == 'paused' ? 'selected' : '' }}>Paused</option>
                                    <option value="stopped" {{ old('status', $recurrentBill->status) == 'stopped' ? 'selected' : '' }}>Stopped</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                            <textarea name="notes" id="notes" rows="2"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $recurrentBill->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Bill Items -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            Bill Items
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
                            Add Item
                        </button>
                    </div>
                </div>

                <!-- Totals -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-end">
                            <div class="w-full md:w-80 space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="'₦' + subtotal.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="'₦' + totalTax.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between text-lg font-bold border-t border-gray-200 dark:border-gray-700 pt-3">
                                    <span class="text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-gray-900 dark:text-gray-100" x-text="'₦' + grandTotal.toFixed(2)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <a href="{{ route('recurrent-bills.show', $recurrentBill) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script nonce="{{ app('csp-nonce') }}">
        function recurrentBillForm() {
            return {
                availableProducts: @json($items->map(fn($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'price' => $i->cost_price ?? $i->selling_price,
                    'description' => $i->name,
                    'tax_rate' => $i->tax_rate ?? 0
                ])),
                items: @json($recurrentBill->items->map(fn($i) => [
                    'item_id' => $i->item_id ?? '',
                    'description' => $i->description,
                    'quantity' => floatval($i->quantity),
                    'unit_price' => floatval($i->unit_price),
                    'tax_rate' => floatval($i->tax_rate),
                    'itemSearch' => '',
                    'itemDropdownOpen' => false,
                    'itemHighlightedIndex' => 0
                ])),
                subtotal: {{ $recurrentBill->subtotal }},
                totalTax: {{ $recurrentBill->tax_amount }},
                grandTotal: {{ $recurrentBill->total }},

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
                    this.items[index].description = product.description || product.name;
                    this.items[index].unit_price = product.price;
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
                    if (option.value) {
                        this.items[index].description = option.dataset.desc || '';
                        this.items[index].unit_price = parseFloat(option.dataset.price) || 0;
                        this.items[index].tax_rate = parseFloat(option.dataset.tax) || 0;
                        this.calculateTotals();
                    }
                },

                lineTotal(index) {
                    const item = this.items[index];
                    const lineSubtotal = item.quantity * item.unit_price;
                    const lineTax = lineSubtotal * (item.tax_rate / 100);
                    return lineSubtotal + lineTax;
                },

                calculateTotals() {
                    this.subtotal = 0;
                    this.totalTax = 0;
                    this.items.forEach(item => {
                        const lineSubtotal = item.quantity * item.unit_price;
                        const lineTax = lineSubtotal * (item.tax_rate / 100);
                        this.subtotal += lineSubtotal;
                        this.totalTax += lineTax;
                    });
                    this.grandTotal = this.subtotal + this.totalTax;
                }
            }
        }
    </script>
</x-app-layout>
