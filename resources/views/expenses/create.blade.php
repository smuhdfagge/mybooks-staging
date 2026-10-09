<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Record New Expense') }}
            </h2>
            <a href="{{ route('expenses.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('expenses.store') }}" method="POST" class="p-6">
                    @csrf
                    <x-lock-date-notice field="expense_date" />

                    <!-- Expense Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Expense Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="expense_number" class="form-label">Expense Number</label>
                                <input type="text" id="expense_number" value="{{ $expenseNumber }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div>
                                <x-field name="name" label="Expense Name" :value="old('name')" required placeholder="e.g. Office Supplies, Travel, Utilities" />
                            </div>

                            <div>
                                <x-field name="expense_date" label="Expense Date" type="date" :value="old('expense_date', date('Y-m-d'))" required />
                            </div>

                            <div>
                                <x-searchable-select
                                    name="expense_account_id"
                                    label="Expense Account *"
                                    :options="$expenseAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                    :value="old('expense_account_id', '')"
                                    placeholder="Select Account"
                                    search-placeholder="Search accounts..."
                                    :has-error="$errors->has('expense_account_id')" />
                                @error('expense_account_id')
                                    <p id="expense_account_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="amount" class="form-label">Amount <span class="text-red-600 dark:text-red-300">*</span></label>
                                <div class="relative">
                                    <input type="number" name="amount" id="amount" value="{{ old('amount') }}" min="0.01" step="0.01" required placeholder="0.00"
                                        class="form-control @error('amount') border-red-500 @enderror" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                                </div>
                                @error('amount')
                                    <p id="amount-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-data="searchableSelect({
                                items: @js($vendors->map(fn ($vendor) => ['id' => (string) $vendor->id, 'name' => $vendor->name . ($vendor->company_name ? " (" . ($vendor->company_name) . ")" : "")])->values()),
                                selectedId: '{{ old('vendor_id', request('vendor_id')) }}'
                            })" class="relative">
                                <label for="vendor_search" class="form-label">Vendor</label>
                                <input type="hidden" name="vendor_id" :value="selectedId" @error('vendor_id') aria-invalid="true" aria-describedby="vendor_id-error" @enderror>
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
                                        No vendors found
                                    </div>
                                </div>
                                @error('vendor_id')
                                    <p id="vendor_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="paid_through_id"
                                    label="Paid Through"
                                    :options="$paymentAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                    :value="old('paid_through_id', '')"
                                    placeholder="Select Account (Optional)"
                                    search-placeholder="Search accounts..."
                                    :has-error="$errors->has('paid_through_id')" />
                                @error('paid_through_id')
                                    <p id="paid_through_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="bank_id"
                                    label="Bank Account"
                                    :options="$banks->mapWithKeys(fn($b) => [$b->id => $b->name . ' (' . $b->account_number . ')'])->toArray()"
                                    :value="old('bank_id', '')"
                                    placeholder="Select Bank Account (Optional)"
                                    search-placeholder="Search banks..."
                                    :has-error="$errors->has('bank_id')" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select which bank account to pay from</p>
                                @error('bank_id')
                                    <p id="bank_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-field name="reference" label="Reference / Receipt #" :value="old('reference')" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Options</label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_billable" value="1" {{ old('is_billable') ? 'checked' : '' }}
                                        class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:text-brand-300">
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Billable to Customer</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Description
                        </h3>
                        <div>
                            <x-field name="description" label="Expense Description" type="textarea" :value="old('description')" rows="3" />
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('expenses.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Record Expense
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
    </script>
    @endpush
</x-app-layout>
