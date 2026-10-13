<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="New budget" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('budgets.store') }}" method="POST" class="p-6">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Budget Name -->
                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Budget Name *</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                   placeholder="e.g., Operating Budget 2026" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name')
                                <p id="name-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Fiscal Year -->
                        <div>
                            <x-searchable-select
                                name="fiscal_year"
                                label="Fiscal Year *"
                                :options="$fiscalYears"
                                :value="old('fiscal_year', (string)date('Y'))"
                                placeholder="Select Year"
                                search-placeholder="Search years..."
                                :has-error="$errors->has('fiscal_year')" />
                            @error('fiscal_year')
                                <p id="fiscal_year-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Copy From Existing -->
                        <div>
                            <x-searchable-select
                                name="copy_from"
                                label="Copy From (Optional)"
                                :options="$existingBudgets->mapWithKeys(fn($b) => [$b->id => $b->name . ' (' . $b->fiscal_year . ')'])->toArray()"
                                :value="old('copy_from', '')"
                                placeholder="-- Start Fresh --"
                                search-placeholder="Search budgets..."
                                :has-error="$errors->has('copy_from')" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Copy budget lines from an existing budget as a starting point.</p>
                            @error('copy_from')
                                <p id="copy_from-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea name="description" id="description" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                      placeholder="Optional notes about this budget..." @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
                            @error('description')
                                <p id="description-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="mt-6 p-4 bg-brand-50 dark:bg-brand-900/20 rounded-lg">
                        <div class="flex">
                            <svg class="w-5 h-5 text-brand-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-brand-800 dark:text-brand-300">Getting Started</h3>
                                <p class="mt-1 text-sm text-brand-700 dark:text-brand-300">
                                    After creating the budget, you'll be able to add or edit budget line items for each account on the edit page.
                                    You can enter monthly amounts for income and expense accounts.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('budgets.index') }}" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            Create Budget
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
