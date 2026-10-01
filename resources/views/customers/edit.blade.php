<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Customer') }}: {{ $customer->name }}
            </h2>
            <a href="{{ route('customers.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 transition">
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
                <form action="{{ route('customers.update', $customer) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <!-- Basic Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Basic Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-field name="name" label="Customer Name" :value="old('name', $customer->name)" required />
                            </div>

                            <div>
                                <x-field name="company_name" label="Company Name" :value="old('company_name', $customer->company_name)" />
                            </div>

                            <div>
                                <x-field name="email" label="Email Address" type="email" :value="old('email', $customer->email)" />
                            </div>

                            <div>
                                <x-field name="phone" label="Phone Number" :value="old('phone', $customer->phone)" />
                            </div>

                            <div>
                                <x-field name="tax_number" label="Tax Number / VAT ID" :value="old('tax_number', $customer->tax_number)" />
                            </div>

                            <div>
                                <x-searchable-select
                                    name="is_active"
                                    label="Status"
                                    :options="['1' => 'Active', '0' => 'Inactive']"
                                    :value="old('is_active', $customer->is_active) ? '1' : '0'"
                                    placeholder="Select Status"
                                    search-placeholder="Search..."
                                    :has-error="$errors->has('is_active')" />
                            </div>
                        </div>
                    </div>

                    @include('withholding-tax._party-fields', ['party' => $customer, 'side' => 'customer'])

                    <!-- Address Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Address Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <x-field name="billing_address" label="Billing Address" type="textarea" :value="old('billing_address', $customer->billing_address)" rows="2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-field name="shipping_address" label="Shipping Address" type="textarea" :value="old('shipping_address', $customer->shipping_address)" rows="2" />
                            </div>

                            <div>
                                <x-field name="city" label="City" :value="old('city', $customer->city)" />
                            </div>

                            <div>
                                <x-searchable-select
                                    name="state"
                                    label="State / Province"
                                    :options="$states->pluck('name')->toArray()"
                                    :value="old('state', $customer->state ?? '')"
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
                                    :value="old('country', $customer->country ?? '')"
                                    placeholder="Select Country"
                                    search-placeholder="Search countries..."
                                    :has-error="$errors->has('country')" />
                                @error('country')
                                    <p id="country-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-field name="postal_code" label="Postal Code" :value="old('postal_code', $customer->postal_code)" />
                            </div>
                        </div>
                    </div>

                    <!-- Payment Terms -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Payment Terms</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="credit_limit" class="form-label">Credit Limit</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">@currencySymbol</span>
                                    <input type="number" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', $customer->credit_limit) }}" min="0" step="0.01"
                                        class="w-full pl-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('credit_limit') border-red-500 @enderror" @error('credit_limit') aria-invalid="true" aria-describedby="credit_limit-error" @enderror>
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
                                    :value="old('payment_terms', (string)$customer->payment_terms)"
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
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Additional Information</h3>
                        <div>
                            <x-field name="notes" label="Notes" type="textarea" :value="old('notes', $customer->notes)" rows="3" />
                        </div>
                    </div>

                    <!-- Customer Stats (Read-only) -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Account Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Total Balance</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($customer->balance, 2) }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Invoices</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $customer->invoices_count ?? $customer->invoices()->count() }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Customer Since</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $customer->created_at->format('M Y') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('customers.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </a>
                        <x-primary-button class="px-4">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Customer
                        </x-primary-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
