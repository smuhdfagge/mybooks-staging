<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Add Bank Account') }}
            </h2>
            <a href="{{ route('banks.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('banks.store') }}" method="POST" class="p-6">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Account Name -->
                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Account Name *</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="e.g., Main Business Account">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Account Type -->
                        <div>
                            <x-searchable-select
                                name="account_type"
                                label="Account Type *"
                                :options="$accountTypes"
                                :value="old('account_type', '')"
                                placeholder="Select Account Type"
                                search-placeholder="Search types..."
                                :has-error="$errors->has('account_type')" />
                            @error('account_type')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Currency -->
                        <div>
                            <x-searchable-select
                                name="currency"
                                label="Currency *"
                                :options="['NGN' => 'NGN - Nigerian Naira', 'USD' => 'USD - US Dollar', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound', 'CAD' => 'CAD - Canadian Dollar', 'AUD' => 'AUD - Australian Dollar', 'INR' => 'INR - Indian Rupee', 'JPY' => 'JPY - Japanese Yen', 'CNY' => 'CNY - Chinese Yuan']"
                                :value="old('currency', 'NGN')"
                                placeholder="Select Currency"
                                search-placeholder="Search currencies..."
                                :has-error="$errors->has('currency')" />
                            @error('currency')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Bank Name -->
                        <div>
                            <label for="bank_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bank Name</label>
                            <input type="text" name="bank_name" id="bank_name" value="{{ old('bank_name') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="e.g., Chase, Bank of America">
                            @error('bank_name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Account Number -->
                        <div>
                            <label for="account_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Account Number</label>
                            <input type="text" name="account_number" id="account_number" value="{{ old('account_number') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('account_number')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Routing Number -->
                        <div>
                            <label for="routing_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Routing Number</label>
                            <input type="text" name="routing_number" id="routing_number" value="{{ old('routing_number') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('routing_number')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- SWIFT Code -->
                        <div>
                            <label for="swift_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">SWIFT/BIC Code</label>
                            <input type="text" name="swift_code" id="swift_code" value="{{ old('swift_code') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('swift_code')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- IBAN -->
                        <div>
                            <label for="iban" class="block text-sm font-medium text-gray-700 dark:text-gray-300">IBAN</label>
                            <input type="text" name="iban" id="iban" value="{{ old('iban') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('iban')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Branch Name -->
                        <div>
                            <label for="branch_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Branch Name</label>
                            <input type="text" name="branch_name" id="branch_name" value="{{ old('branch_name') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('branch_name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Branch Address -->
                        <div>
                            <label for="branch_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Branch Address</label>
                            <input type="text" name="branch_address" id="branch_address" value="{{ old('branch_address') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('branch_address')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Opening Balance -->
                        <div>
                            <label for="opening_balance" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Opening Balance</label>
                            <input type="number" step="0.01" name="opening_balance" id="opening_balance" value="{{ old('opening_balance', '0.00') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('opening_balance')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Opening Balance Date -->
                        <div>
                            <label for="opening_balance_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Opening Balance Date</label>
                            <input type="date" name="opening_balance_date" id="opening_balance_date" value="{{ old('opening_balance_date', date('Y-m-d')) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('opening_balance_date')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Chart of Account -->
                        <div class="md:col-span-2">
                            <x-searchable-select
                                name="chart_of_account_id"
                                label="Linked Chart of Account"
                                :options="$chartOfAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                :value="old('chart_of_account_id', '')"
                                placeholder="-- Select Account (Optional) --"
                                search-placeholder="Search accounts..."
                                :has-error="$errors->has('chart_of_account_id')" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Link this bank account to an asset account in your Chart of Accounts for journal entries.</p>
                            @error('chart_of_account_id')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea name="description" id="description" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Is Primary -->
                        <div class="md:col-span-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_primary" value="1" {{ old('is_primary') ? 'checked' : '' }}
                                       class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Set as Primary Account</span>
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Primary account is used as the default for payments and deposits.</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('banks.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-400 dark:hover:bg-gray-500 focus:bg-gray-400 dark:focus:bg-gray-500 active:bg-gray-500 dark:active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Create Bank Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
