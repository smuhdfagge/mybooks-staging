<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tax-groups.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Tax Group') }}
            </h2>
        </div>
    </x-slot>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <form action="{{ route('tax-groups.update', $taxGroup) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <x-input-label for="name" :value="__('Group Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $taxGroup->name)" required placeholder="e.g., Combined Tax" />
                        <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- Code -->
                    <div>
                        <x-input-label for="code" :value="__('Code')" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code', $taxGroup->code)" maxlength="20" placeholder="e.g., COMB" />
                        <x-input-error id="code-error" :messages="$errors->get('code')" class="mt-2" />
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <x-input-label for="description" :value="__('Description')" />
                    <textarea name="description" id="description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" placeholder="Optional description" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $taxGroup->description) }}</textarea>
                    <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                </div>

                <!-- Tax Rates Selection -->
                @php
                    $selectedTaxRates = old('tax_rates', $taxGroup->taxRates->pluck('id')->toArray());
                @endphp
                <div class="mt-6">
                    <x-input-label :value="__('Select Tax Rates')" />
                    <p class="text-gray-500 dark:text-gray-400 text-xs mb-3">Select the tax rates to include in this group.</p>
                    
                    @if($taxRates->count() > 0)
                        <div class="border border-gray-300 dark:border-gray-600 rounded-lg divide-y divide-gray-200 dark:divide-gray-600 max-h-96 overflow-y-auto">
                            @foreach($taxRates as $taxRate)
                                <label class="flex items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                    <input type="checkbox" name="tax_rates[]" value="{{ $taxRate->id }}" 
                                        {{ in_array($taxRate->id, $selectedTaxRates) ? 'checked' : '' }}
                                        class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <div class="ml-3 flex-1">
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $taxRate->name }}</span>
                                        @if($taxRate->code)
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">({{ $taxRate->code }})</span>
                                        @endif
                                        <span class="ml-2 font-semibold text-blue-600 dark:text-blue-400">{{ $taxRate->formatted_rate }}</span>
                                        @if($taxRate->is_compound)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Compound</span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 capitalize">{{ $taxRate->applies_to }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center text-gray-500 dark:text-gray-400">
                            <p>No tax rates available.</p>
                            <a href="{{ route('tax-rates.create') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Create a tax rate first</a>
                        </div>
                    @endif
                    <x-input-error id="tax_rates-error" :messages="$errors->get('tax_rates')" class="mt-2" />
                </div>

                <!-- Options -->
                <div class="mt-6 space-y-4">
                    <label class="flex items-center">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" {{ old('is_default', $taxGroup->is_default) ? 'checked' : '' }} class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Set as Default Tax Group</span>
                    </label>

                    <label class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $taxGroup->is_active) ? 'checked' : '' }} class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                    </label>
                </div>

                <div class="mt-8 flex justify-end gap-4">
                    <a href="{{ route('tax-groups.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <x-primary-button>Update Tax Group</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
