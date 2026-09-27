<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Expense') }} - {{ $expense->expense_number }}
            </h2>
            <a href="{{ route('expenses.show', $expense) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Details
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Status Information -->
            @if($expense->isRejected())
                <div class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 dark:border-yellow-600 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-400">Expense Previously Rejected</h3>
                            @if($expense->rejection_reason)
                                <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">Reason: {{ $expense->rejection_reason }}</p>
                            @endif
                            <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">Make the necessary changes and submit for approval again.</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('expenses.update', $expense) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <!-- Expense Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Expense Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="expense_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expense Number</label>
                                <input type="text" id="expense_number" value="{{ $expense->expense_number }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expense Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" value="{{ old('name', $expense->name) }}" required placeholder="e.g. Office Supplies, Travel, Utilities"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="expense_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expense Date <span class="text-red-500">*</span></label>
                                <input type="date" name="expense_date" id="expense_date" value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('expense_date') border-red-500 @enderror">
                                @error('expense_date')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="expense_account_id"
                                    label="Expense Account *"
                                    :options="$expenseAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                    :value="old('expense_account_id', $expense->expense_account_id ?? '')"
                                    placeholder="Select Account"
                                    search-placeholder="Search accounts..."
                                    :has-error="$errors->has('expense_account_id')" />
                                @error('expense_account_id')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">₦</span>
                                    <input type="number" name="amount" id="amount" value="{{ old('amount', $expense->amount) }}" min="0.01" step="0.01" required
                                        class="w-full pl-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror">
                                </div>
                                @error('amount')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-data="searchableSelect({
                                items: {{ json_encode($vendors->map(fn($v) => ['id' => $v->id, 'name' => $v->name . ($v->company_name ? \" ({$v->company_name})\" : '')])) }},
                                selected: '{{ old('vendor_id', $expense->vendor_id) }}',
                                placeholder: 'Select Vendor (Optional)'
                            })">
                                <label for="vendor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vendor</label>
                                <input type="hidden" name="vendor_id" :value="selectedId">
                                <div class="relative">
                                    <input type="text" 
                                        x-model="search" 
                                        @click="open = true" 
                                        @keydown.arrow-down.prevent="highlightedIndex = Math.min(highlightedIndex + 1, filteredItems.length - 1)"
                                        @keydown.arrow-up.prevent="highlightedIndex = Math.max(highlightedIndex - 1, 0)"
                                        @keydown.enter.prevent="if(filteredItems[highlightedIndex]) selectItem(filteredItems[highlightedIndex])"
                                        @keydown.escape="open = false"
                                        placeholder="Select Vendor (Optional)"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('vendor_id') border-red-500 @enderror">
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
                                @error('vendor_id')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="paid_through_id"
                                    label="Paid Through"
                                    :options="$paymentAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                    :value="old('paid_through_id', $expense->paid_through_id ?? '')"
                                    placeholder="Select Account (Optional)"
                                    search-placeholder="Search accounts..."
                                    :has-error="$errors->has('paid_through_id')" />
                                @error('paid_through_id')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="bank_id"
                                    label="Bank Account"
                                    :options="$banks->mapWithKeys(fn($b) => [$b->id => $b->name . ' (' . $b->account_number . ')'])->toArray()"
                                    :value="old('bank_id', $expense->bank_id ?? '')"
                                    placeholder="Select Bank Account (Optional)"
                                    search-placeholder="Search banks..."
                                    :has-error="$errors->has('bank_id')" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select which bank account to pay from</p>
                                @error('bank_id')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference / Receipt #</label>
                                <input type="text" name="reference" id="reference" value="{{ old('reference', $expense->reference) }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('reference') border-red-500 @enderror">
                                @error('reference')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Options</label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_billable" value="1" {{ old('is_billable', $expense->is_billable) ? 'checked' : '' }}
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expense Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror">{{ old('description', $expense->description) }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('expenses.show', $expense) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Expense
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
    </script>
    @endpush
</x-app-layout>
