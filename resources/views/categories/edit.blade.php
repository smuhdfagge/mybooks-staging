<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Category') }} - {{ $category->name }}
            </h2>
            <a href="{{ route('fixed-asset-categories.show', $category) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Cancel
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('fixed-asset-categories.update', $category) }}">
                        @csrf
                        @method('PUT')

                        <!-- Basic Information -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium mb-2">Category Name *</label>
                                    <input type="text" name="name" id="name" value="{{ old('name', $category->name) }}" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                                    @error('name')
                                        <p id="name-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="code" class="block text-sm font-medium mb-2">Category Code</label>
                                    <input type="text" name="code" id="code" value="{{ old('code', $category->code) }}"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                        placeholder="e.g., COMP, FURN, VEH" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                                    @error('code')
                                        <p id="code-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="description" class="block text-sm font-medium mb-2">Description</label>
                                    <textarea name="description" id="description" rows="3"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $category->description) }}</textarea>
                                    @error('description')
                                        <p id="description-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Depreciation Settings -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Default Depreciation Settings</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="default_useful_life" class="block text-sm font-medium mb-2">Default Useful Life (years)</label>
                                    <input type="number" name="default_useful_life" id="default_useful_life" value="{{ old('default_useful_life', $category->default_useful_life) }}" min="1" step="0.5"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('default_useful_life') aria-invalid="true" aria-describedby="default_useful_life-error" @enderror>
                                    @error('default_useful_life')
                                        <p id="default_useful_life-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <x-searchable-select
                                        name="default_depreciation_method"
                                        label="Default Depreciation Method"
                                        :options="['straight_line' => 'Straight Line', 'declining_balance' => 'Declining Balance', 'double_declining' => 'Double Declining Balance', 'sum_of_years' => 'Sum of Years Digits']"
                                        :value="old('default_depreciation_method', $category->default_depreciation_method ?? 'straight_line')"
                                        placeholder="Select Method"
                                        search-placeholder="Search methods..."
                                        :has-error="$errors->has('default_depreciation_method')" />
                                    @error('default_depreciation_method')
                                        <p id="default_depreciation_method-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Chart of Accounts -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Chart of Accounts</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <x-searchable-select
                                        name="asset_account_id"
                                        label="Asset Account"
                                        :options="$assetAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                        :value="old('asset_account_id', $category->asset_account_id ?? '')"
                                        placeholder="Select Account"
                                        search-placeholder="Search accounts..."
                                        :has-error="$errors->has('asset_account_id')" />
                                    @error('asset_account_id')
                                        <p id="asset_account_id-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <x-searchable-select
                                        name="accumulated_depreciation_account_id"
                                        label="Accumulated Depreciation Account"
                                        :options="$contraAssetAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                        :value="old('accumulated_depreciation_account_id', $category->accumulated_depreciation_account_id ?? '')"
                                        placeholder="Select Account"
                                        search-placeholder="Search accounts..."
                                        :has-error="$errors->has('accumulated_depreciation_account_id')" />
                                    @error('accumulated_depreciation_account_id')
                                        <p id="accumulated_depreciation_account_id-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <x-searchable-select
                                        name="depreciation_expense_account_id"
                                        label="Depreciation Expense Account"
                                        :options="$expenseAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                        :value="old('depreciation_expense_account_id', $category->depreciation_expense_account_id ?? '')"
                                        placeholder="Select Account"
                                        search-placeholder="Search accounts..."
                                        :has-error="$errors->has('depreciation_expense_account_id')" />
                                    @error('depreciation_expense_account_id')
                                        <p id="depreciation_expense_account_id-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <x-searchable-select
                                        name="gain_loss_account_id"
                                        label="Gain/Loss on Disposal Account"
                                        :options="$otherAccounts->mapWithKeys(fn($a) => [$a->id => $a->account_code . ' - ' . $a->name])->toArray()"
                                        :value="old('gain_loss_account_id', $category->gain_loss_account_id ?? '')"
                                        placeholder="Select Account"
                                        search-placeholder="Search accounts..."
                                        :has-error="$errors->has('gain_loss_account_id')" />
                                    @error('gain_loss_account_id')
                                        <p id="gain_loss_account_id-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('fixed-asset-categories.show', $category) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Update Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
