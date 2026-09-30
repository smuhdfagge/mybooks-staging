<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Recurrent Expense Profile') }} - {{ $recurrentExpense->profile_name }}
            </h2>
            <a href="{{ route('recurrent-expenses.show', $recurrentExpense) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Details
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('recurrent-expenses.update', $recurrentExpense) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <!-- Profile Details -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Profile Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="profile_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Profile Name <span class="text-red-500">*</span></label>
                                <input type="text" name="profile_name" id="profile_name" value="{{ old('profile_name', $recurrentExpense->profile_name) }}" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('profile_name') border-red-500 @enderror">
                                @error('profile_name')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="expense_account_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expense Account <span class="text-red-500">*</span></label>
                                <select name="expense_account_id" id="expense_account_id" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('expense_account_id') border-red-500 @enderror">
                                    <option value="">Select Account</option>
                                    @foreach($expenseAccounts as $account)
                                        <option value="{{ $account->id }}" {{ old('expense_account_id', $recurrentExpense->expense_account_id) == $account->id ? 'selected' : '' }}>
                                            {{ $account->account_code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('expense_account_id')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">@currencySymbol</span>
                                    <input type="number" name="amount" id="amount" value="{{ old('amount', $recurrentExpense->amount) }}" min="0.01" step="0.01" required
                                        class="w-full pl-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror">
                                </div>
                                @error('amount')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-data="searchableSelect({
                                items: {{ json_encode($vendors->map(fn($v) => ['id' => $v->id, 'name' => $v->name . ($v->company_name ? " ({$v->company_name})" : '')])) }},
                                selected: '{{ old('vendor_id', $recurrentExpense->vendor_id) }}',
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
                                <label for="paid_through_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paid Through</label>
                                <select name="paid_through_id" id="paid_through_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('paid_through_id') border-red-500 @enderror">
                                    <option value="">Select Account (Optional)</option>
                                    @foreach($paymentAccounts as $account)
                                        <option value="{{ $account->id }}" {{ old('paid_through_id', $recurrentExpense->paid_through_id) == $account->id ? 'selected' : '' }}>
                                            {{ $account->account_code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('paid_through_id')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Schedule
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="frequency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Frequency <span class="text-red-500">*</span></label>
                                <select name="frequency" id="frequency" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('frequency') border-red-500 @enderror">
                                    <option value="weekly" {{ old('frequency', $recurrentExpense->frequency) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="monthly" {{ old('frequency', $recurrentExpense->frequency) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="quarterly" {{ old('frequency', $recurrentExpense->frequency) == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                    <option value="yearly" {{ old('frequency', $recurrentExpense->frequency) == 'yearly' ? 'selected' : '' }}>Yearly</option>
                                </select>
                                @error('frequency')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                                <input type="text" id="start_date" value="{{ $recurrentExpense->start_date->format('M d, Y') }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                                <input type="date" name="end_date" id="end_date" value="{{ old('end_date', $recurrentExpense->end_date?->format('Y-m-d')) }}"
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
                                    <option value="active" {{ old('status', $recurrentExpense->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="paused" {{ old('status', $recurrentExpense->status) == 'paused' ? 'selected' : '' }}>Paused</option>
                                    <option value="stopped" {{ old('status', $recurrentExpense->status) == 'stopped' ? 'selected' : '' }}>Stopped</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Info -->
                    <div class="mb-8">
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea name="description" id="description" rows="3"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $recurrentExpense->description) }}</textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('recurrent-expenses.show', $recurrentExpense) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
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
