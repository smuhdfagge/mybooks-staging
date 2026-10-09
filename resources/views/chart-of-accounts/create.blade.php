<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create New Account') }}
            </h2>
            <a href="{{ route('chart-of-accounts.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
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
                <form action="{{ route('chart-of-accounts.store') }}" method="POST" class="p-6">
                    @csrf

                    <!-- Account Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Account Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="account_code" class="form-label">Account Code <span class="text-red-600 dark:text-red-300">*</span></label>
                                <input type="text" name="account_code" id="account_code" value="{{ old('account_code') }}" required
                                    class="form-control @error('account_code') border-red-500 @enderror"
                                    placeholder="e.g., 1000, 2000, 3000" @error('account_code') aria-invalid="true" aria-describedby="account_code-error" @enderror>
                                @error('account_code')
                                    <p id="account_code-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="name" class="form-label">Account Name <span class="text-red-600 dark:text-red-300">*</span></label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    class="form-control @error('name') border-red-500 @enderror"
                                    placeholder="e.g., Cash, Accounts Receivable" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                                @error('name')
                                    <p id="name-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="type"
                                    label="Account Type *"
                                    :options="$types"
                                    :value="old('type', '')"
                                    placeholder="Select Type"
                                    search-placeholder="Search types..."
                                    :has-error="$errors->has('type')" />
                                @error('type')
                                    <p id="type-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sub_type" class="form-label">Sub Type</label>
                                <input type="text" name="sub_type" id="sub_type" value="{{ old('sub_type') }}"
                                    class="form-control @error('sub_type') border-red-500 @enderror"
                                    placeholder="e.g., Current Asset, Fixed Asset" @error('sub_type') aria-invalid="true" aria-describedby="sub_type-error" @enderror>
                                @error('sub_type')
                                    <p id="sub_type-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="parent_id"
                                    label="Parent Account"
                                    :options="$accounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                    :value="old('parent_id', '')"
                                    placeholder="None (Top Level)"
                                    search-placeholder="Search accounts..."
                                    :has-error="$errors->has('parent_id')" />
                                @error('parent_id')
                                    <p id="parent_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="opening_balance" class="form-label">Opening Balance</label>
                                <div class="relative">
                                    <input type="number" name="opening_balance" id="opening_balance" value="{{ old('opening_balance', '0.00') }}" step="0.01" placeholder="0.00"
                                        class="form-control @error('opening_balance') border-red-500 @enderror" @error('opening_balance') aria-invalid="true" aria-describedby="opening_balance-error" @enderror>
                                </div>
                                @error('opening_balance')
                                    <p id="opening_balance-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
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
                            <label for="description" class="form-label">Account Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="form-control @error('description') border-red-500 @enderror"
                                placeholder="Describe the purpose of this account..." @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
                            @error('description')
                                <p id="description-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('chart-of-accounts.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
