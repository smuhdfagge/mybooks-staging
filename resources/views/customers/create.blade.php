<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Add New Customer') }}
            </h2>
            <a href="{{ route('customers.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('customers.store') }}" method="POST" class="p-6">
                    @csrf

                    <!-- Basic Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Basic Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-field name="name" label="Customer Name" :value="old('name')" required />
                            </div>

                            <div>
                                <x-field name="company_name" label="Company Name" :value="old('company_name')" />
                            </div>

                            <div>
                                <x-field name="email" label="Email Address" type="email" :value="old('email')" />
                            </div>

                            <div>
                                <x-field name="phone" label="Phone Number" :value="old('phone')" />
                            </div>

                            <div>
                                <x-field name="tax_number" label="TIN (Tax ID)" :value="old('tax_number')" help="Needed for withholding tax schedules and certificates." />
                            </div>

                            <div>
                                <x-field name="entity_type" label="Type (for withholding tax)" type="select">
                                    <option value="company" @selected(old('entity_type', 'company') === 'company')>Company or business</option>
                                    <option value="individual" @selected(old('entity_type', 'company') === 'individual')>Individual</option>
                                </x-field>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Address Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <x-field name="billing_address" label="Billing Address" type="textarea" :value="old('billing_address')" rows="2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-field name="shipping_address" label="Shipping Address" type="textarea" :value="old('shipping_address')" rows="2" />
                            </div>

                            <div>
                                <x-field name="city" label="City" :value="old('city')" />
                            </div>

                            <div>
                                <x-searchable-select
                                    name="state"
                                    label="State / Province"
                                    :options="$states->pluck('name')->toArray()"
                                    :value="old('state', '')"
                                    placeholder="Select State"
                                    search-placeholder="Search states..."
                                    :has-error="$errors->has('state')" />
                                @error('state')
                                    <p id="state-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="country"
                                    label="Country"
                                    :options="$countries->pluck('name')->toArray()"
                                    :value="old('country', '')"
                                    placeholder="Select Country"
                                    search-placeholder="Search countries..."
                                    :has-error="$errors->has('country')" />
                                @error('country')
                                    <p id="country-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-field name="postal_code" label="Postal Code" :value="old('postal_code')" />
                            </div>
                        </div>
                    </div>

                    <!-- Payment Terms -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            Payment Terms
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="credit_limit" class="form-label">Credit Limit</label>
                                <div class="relative">
                                    <input type="number" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', 0) }}" min="0" step="0.01" placeholder="0.00"
                                        class="form-control @error('credit_limit') border-red-500 @enderror" @error('credit_limit') aria-invalid="true" aria-describedby="credit_limit-error" @enderror>
                                </div>
                                @error('credit_limit')
                                    <p id="credit_limit-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="payment_terms"
                                    label="Payment Terms (Days)"
                                    :options="['0' => 'Due on Receipt', '7' => 'Net 7', '15' => 'Net 15', '30' => 'Net 30', '45' => 'Net 45', '60' => 'Net 60', '90' => 'Net 90']"
                                    :value="old('payment_terms', '0')"
                                    placeholder="Select Terms"
                                    search-placeholder="Search terms..."
                                    :has-error="$errors->has('payment_terms')" />
                                @error('payment_terms')
                                    <p id="payment_terms-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Additional Information
                        </h3>
                        <div>
                            <x-field name="notes" label="Notes" type="textarea" :value="old('notes')" rows="3" />
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('customers.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <x-primary-button class="px-4">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Customer
                        </x-primary-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
