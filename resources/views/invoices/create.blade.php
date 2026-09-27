<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create Invoice') }}
            </h2>
            <a href="{{ route('invoices.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <x-form-auto-save formKey="invoice-create">
            <form action="{{ route('invoices.store') }}" method="POST" x-data="invoiceForm()" class="space-y-6">
                @csrf

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Invoice Header -->
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Invoice Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div>
                                <label for="invoice_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice Number</label>
                                <input type="text" id="invoice_number" value="{{ $invoiceNumber }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div x-data="searchableSelect({
                                items: [
                                    @foreach($customers as $customer)
                                    { id: '{{ $customer->id }}', name: '{{ addslashes($customer->name) }}{{ $customer->company_name ? " (" . addslashes($customer->company_name) . ")" : "" }}' },
                                    @endforeach
                                ],
                                selectedId: '{{ old('customer_id', request('customer_id')) }}'
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
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
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
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="invoice_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice Date <span class="text-red-500">*</span></label>
                                <input type="date" name="invoice_date" id="invoice_date" value="{{ old('invoice_date', date('Y-m-d')) }}" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('invoice_date') border-red-500 @enderror">
                                @error('invoice_date')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date <span class="text-red-500">*</span></label>
                                <input type="date" name="due_date" id="due_date" value="{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('due_date') border-red-500 @enderror">
                                @error('due_date')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference / PO Number</label>
                            <input type="text" name="reference" id="reference" value="{{ old('reference') }}"
                                class="w-full md:w-1/2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            Invoice Items
                        </h3>
                        
                        <div class="overflow-visible">
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
                                                    <input 
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
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    <button type="button" @click="item.itemDropdownOpen = !item.itemDropdownOpen" class="absolute inset-y-0 right-0 flex items-center pr-2">
                                                        <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                    <div 
                                                        x-show="item.itemDropdownOpen" 
                                                        @click.away="item.itemDropdownOpen = false"
                                                        x-transition:enter="transition ease-out duration-100"
                                                        x-transition:enter-start="opacity-0 scale-95"
                                                        x-transition:enter-end="opacity-100 scale-100"
                                                        x-transition:leave="transition ease-in duration-75"
                                                        x-transition:leave-start="opacity-100 scale-100"
                                                        x-transition:leave-end="opacity-0 scale-95"
                                                        class="absolute z-[100] bottom-full mb-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none text-sm">
                                                        <template x-for="(product, pIndex) in getFilteredProducts(item.itemSearch)" :key="product.id">
                                                            <div 
                                                                @click="selectProduct(index, product)"
                                                                @mouseenter="item.itemHighlightedIndex = pIndex"
                                                                :class="{ 'bg-indigo-600 text-white': item.itemHighlightedIndex === pIndex, 'text-gray-900 dark:text-gray-100': item.itemHighlightedIndex !== pIndex }"
                                                                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white">
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
                                            <td class="py-2 text-right text-sm font-medium text-gray-900 dark:text-gray-100" x-text="lineTotal(index).toFixed(2)"></td>
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
                    <!-- Notes & Terms -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Notes & Terms
                            </h3>
                            <div class="mb-4">
                                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (visible on invoice)</label>
                                <textarea name="notes" id="notes" rows="3"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                            </div>
                            <div>
                                <label for="terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Terms & Conditions</label>
                                <textarea name="terms" id="terms" rows="3"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('terms', 'Payment is due within the specified terms. Late payments may incur additional charges.') }}</textarea>
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
                                Invoice Summary
                            </h3>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="subtotal.toFixed(2)">0.00</span>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-600 dark:text-gray-400">Discount</span>
                                        <select name="discount_type" x-model="discountType" @change="calculateTotals()"
                                            class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1">
                                            <option value="">None</option>
                                            <option value="percentage">%</option>
                                            <option value="fixed">₦</option>
                                        </select>
                                        <input type="number" name="discount_amount" x-model.number="discountValue" x-show="discountType" min="0" step="0.01"
                                            @input="calculateTotals()"
                                            class="w-20 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1">
                                    </div>
                                    <span class="font-medium text-red-600 dark:text-red-400" x-text="'-' + discount.toFixed(2)">-0.00</span>
                                </div>

                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Tax</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="totalTax.toFixed(2)">0.00</span>
                                </div>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400" x-text="total.toFixed(2)">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('invoices.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Create Invoice
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
                    // Set initial search text if there's a selected item
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

        function invoiceForm() {
            return {
                items: [{ item_id: '', description: '', quantity: 1, unit_price: 0, tax_rate: 0, is_taxable: false, itemSearch: '', itemDropdownOpen: false, itemHighlightedIndex: 0 }],
                availableProducts: [
                    @foreach($items as $item)
                    { id: '{{ $item->id }}', name: '{{ addslashes($item->name) }}{{ $item->is_taxable ? " (Taxable)" : "" }}', price: {{ $item->selling_price }}, desc: '{{ addslashes($item->description ?? $item->name) }}', taxable: {{ $item->is_taxable ? 'true' : 'false' }}, tax: {{ $item->effective_tax_rate ?? 0 }} },
                    @endforeach
                ],
                discountType: '',
                discountValue: 0,
                subtotal: 0,
                totalTax: 0,
                discount: 0,
                total: 0,

                addItem() {
                    this.items.push({ item_id: '', description: '', quantity: 1, unit_price: 0, tax_rate: 0, is_taxable: false, itemSearch: '', itemDropdownOpen: false, itemHighlightedIndex: 0 });
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
                    this.items[index].description = product.desc || product.name;
                    this.items[index].is_taxable = product.taxable;
                    this.items[index].tax_rate = product.taxable ? product.tax : 0;
                    this.items[index].itemDropdownOpen = false;
                    this.calculateTotals();
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

                    if (this.discountType === 'percentage') {
                        this.discount = this.subtotal * ((this.discountValue || 0) / 100);
                    } else if (this.discountType === 'fixed') {
                        this.discount = this.discountValue || 0;
                    } else {
                        this.discount = 0;
                    }

                    this.total = this.subtotal + this.totalTax - this.discount;
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
