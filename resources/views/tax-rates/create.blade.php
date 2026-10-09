<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('tax-rates.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create Tax Rate') }}
            </h2>
        </div>
    </x-slot>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <form action="{{ route('tax-rates.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <x-input-label for="name" :value="__('Tax Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required placeholder="e.g., VAT, GST, Sales Tax" />
                        <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- Code -->
                    <div>
                        <x-input-label for="code" :value="__('Code')" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code')" maxlength="20" placeholder="e.g., VAT, GST, ST" />
                        <x-input-error id="code-error" :messages="$errors->get('code')" class="mt-2" />
                    </div>

                    <!-- Rate -->
                    <div>
                        <x-input-label for="rate" :value="__('Rate (%)')" />
                        <x-text-input id="rate" name="rate" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full" :value="old('rate')" required placeholder="e.g., 7.5" />
                        <x-input-error id="rate-error" :messages="$errors->get('rate')" class="mt-2" />
                    </div>

                    <!-- Tax Number -->
                    <div>
                        <x-input-label for="tax_number" :value="__('Tax Registration Number')" />
                        <x-text-input id="tax_number" name="tax_number" type="text" class="mt-1 block w-full" :value="old('tax_number')" placeholder="Your tax registration number" />
                        <x-input-error id="tax_number-error" :messages="$errors->get('tax_number')" class="mt-2" />
                    </div>

                    <!-- Type -->
                    <div>
                        <x-input-label for="type" :value="__('Type')" />
                        <select name="type" id="type" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm" @error('type') aria-invalid="true" aria-describedby="type-error" @enderror>
                            <option value="exclusive" {{ old('type', 'exclusive') == 'exclusive' ? 'selected' : '' }}>Exclusive (added to price)</option>
                            <option value="inclusive" {{ old('type') == 'inclusive' ? 'selected' : '' }}>Inclusive (included in price)</option>
                        </select>
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Exclusive: Tax added on top. Inclusive: Tax included in price.</p>
                        <x-input-error id="type-error" :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <!-- Applies To -->
                    <div>
                        <x-input-label for="applies_to" :value="__('Applies To')" />
                        <select name="applies_to" id="applies_to" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm" @error('applies_to') aria-invalid="true" aria-describedby="applies_to-error" @enderror>
                            <option value="both" {{ old('applies_to', 'both') == 'both' ? 'selected' : '' }}>Both Sales & Purchases</option>
                            <option value="sales" {{ old('applies_to') == 'sales' ? 'selected' : '' }}>Sales Only</option>
                            <option value="purchases" {{ old('applies_to') == 'purchases' ? 'selected' : '' }}>Purchases Only</option>
                        </select>
                        <x-input-error id="applies_to-error" :messages="$errors->get('applies_to')" class="mt-2" />
                    </div>
                </div>

                <!-- VAT treatment (for the VAT return) -->
                <div class="mt-6">
                    <x-input-label for="vat_treatment" :value="__('VAT treatment')" />
                    <select name="vat_treatment" id="vat_treatment" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm" @error('vat_treatment') aria-invalid="true" aria-describedby="vat_treatment-error" @enderror>
                        <option value="">Not set</option>
                        @foreach (\App\Services\Accounting\VatTreatment::labels() as $value => $label)
                            <option value="{{ $value }}" {{ old('vat_treatment') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">A rate above 0% is standard-rated. For a 0% rate choose zero-rated (e.g. basic food, medical, exports), exempt, or outside the scope of VAT. The VAT return uses this.</p>
                    <x-input-error id="vat_treatment-error" :messages="$errors->get('vat_treatment')" class="mt-2" />
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <x-input-label for="description" :value="__('Description')" />
                    <textarea name="description" id="description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm" placeholder="Optional description" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
                    <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                </div>

                <!-- Options -->
                <div class="mt-6 space-y-4">
                    <label class="flex items-center">
                        <input type="hidden" name="is_compound" value="0">
                        <input type="checkbox" name="is_compound" value="1" {{ old('is_compound') ? 'checked' : '' }} class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-brand-600 shadow-sm focus:ring-brand-500 dark:focus:ring-brand-600 dark:text-brand-300">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Compound Tax <span class="text-xs">(calculated on top of other taxes)</span></span>
                    </label>

                    <label class="flex items-center">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }} class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-brand-600 shadow-sm focus:ring-brand-500 dark:focus:ring-brand-600 dark:text-brand-300">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Set as Default Tax Rate</span>
                    </label>

                    <label class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-brand-600 shadow-sm focus:ring-brand-500 dark:focus:ring-brand-600 dark:text-brand-300">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                    </label>
                </div>

                <div class="mt-8 flex justify-end gap-4">
                    <a href="{{ route('tax-rates.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <x-primary-button>Create Tax Rate</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
