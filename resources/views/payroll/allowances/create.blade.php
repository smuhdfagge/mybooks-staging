<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Add Allowance') }}
            </h2>
            <a href="{{ route('allowances.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form action="{{ route('allowances.store') }}" method="POST">
                        @csrf

                        <div class="space-y-6">
                            {{-- Name --}}
                            <div>
                                <label for="name" class="form-label">Name <span class="text-red-600 dark:text-red-300">*</span></label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm"
                                    placeholder="e.g. Housing Allowance" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                                @error('name')
                                    <p id="name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Amount Type --}}
                            <div>
                                <label for="amount_type" class="form-label">Amount Type <span class="text-red-600 dark:text-red-300">*</span></label>
                                <select name="amount_type" id="amount_type" required
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm" @error('amount_type') aria-invalid="true" aria-describedby="amount_type-error" @enderror>
                                    <option value="fixed" {{ old('amount_type') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                    <option value="percentage" {{ old('amount_type') === 'percentage' ? 'selected' : '' }}>% of Basic Salary</option>
                                </select>
                                @error('amount_type')
                                    <p id="amount_type-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Amount --}}
                            <div>
                                <label for="amount" class="form-label">Amount <span class="text-red-600 dark:text-red-300">*</span></label>
                                <input type="number" name="amount" id="amount" value="{{ old('amount') }}" required step="0.01" min="0"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm"
                                    placeholder="0.00" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                                @error('amount')
                                    <p id="amount-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Is Taxable --}}
                            <div>
                                <label for="is_taxable" class="form-label">Taxable</label>
                                <select name="is_taxable" id="is_taxable"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm" @error('is_taxable') aria-invalid="true" aria-describedby="is_taxable-error" @enderror>
                                    <option value="1" {{ old('is_taxable', '1') === '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ old('is_taxable') === '0' ? 'selected' : '' }}>No</option>
                                </select>
                                @error('is_taxable')
                                    <p id="is_taxable-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Description --}}
                            <div>
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" rows="3"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm"
                                    placeholder="Optional description" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
                                @error('description')
                                    <p id="description-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                Save Allowance
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
