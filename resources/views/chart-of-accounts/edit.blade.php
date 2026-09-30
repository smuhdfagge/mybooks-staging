<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Account') }} - {{ $chartOfAccount->account_code }}
            </h2>
            <a href="{{ route('chart-of-accounts.show', $chartOfAccount) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
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
                <form action="{{ route('chart-of-accounts.update', $chartOfAccount) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <!-- Account Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Account Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="account_code" class="form-label">Account Code <span class="text-red-500">*</span></label>
                                <input type="text" name="account_code" id="account_code" value="{{ old('account_code', $chartOfAccount->account_code) }}" required
                                    class="form-control @error('account_code') border-red-500 @enderror"
                                    placeholder="e.g., 1000, 2000, 3000" @error('account_code') aria-invalid="true" aria-describedby="account_code-error" @enderror>
                                @error('account_code')
                                    <p id="account_code-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="name" class="form-label">Account Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" value="{{ old('name', $chartOfAccount->name) }}" required
                                    class="form-control @error('name') border-red-500 @enderror"
                                    placeholder="e.g., Cash, Accounts Receivable" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                                @error('name')
                                    <p id="name-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="type"
                                    label="Account Type *"
                                    :options="$types"
                                    :value="old('type', $chartOfAccount->type ?? '')"
                                    placeholder="Select Type"
                                    search-placeholder="Search types..."
                                    :has-error="$errors->has('type')" />
                                @error('type')
                                    <p id="type-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sub_type" class="form-label">Sub Type</label>
                                <input type="text" name="sub_type" id="sub_type" value="{{ old('sub_type', $chartOfAccount->sub_type) }}"
                                    class="form-control @error('sub_type') border-red-500 @enderror"
                                    placeholder="e.g., Current Asset, Fixed Asset" @error('sub_type') aria-invalid="true" aria-describedby="sub_type-error" @enderror>
                                @error('sub_type')
                                    <p id="sub_type-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="parent_id"
                                    label="Parent Account"
                                    :options="$accounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                    :value="old('parent_id', $chartOfAccount->parent_id ?? '')"
                                    placeholder="None (Top Level)"
                                    search-placeholder="Search accounts..."
                                    :has-error="$errors->has('parent_id')" />
                                @error('parent_id')
                                    <p id="parent_id-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Status</label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $chartOfAccount->is_active) ? 'checked' : '' }}
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Active Account</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Balance Information (Read Only) -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Balance Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="form-label">Opening Balance</label>
                                <input type="text" value="{{ number_format($chartOfAccount->opening_balance, 2) }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="form-label">Current Balance</label>
                                <input type="text" value="{{ number_format($chartOfAccount->current_balance, 2) }}" disabled
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 dark:text-gray-300 shadow-sm">
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
                                placeholder="Describe the purpose of this account..." @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $chartOfAccount->description) }}</textarea>
                            @error('description')
                                <p id="description-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('chart-of-accounts.show', $chartOfAccount) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
