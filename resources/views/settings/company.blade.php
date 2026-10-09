<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Company Profile') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('settings.company.update') }}" method="POST" enctype="multipart/form-data" class="p-6">
                    @csrf

                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Basic Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Company Name -->
                            <div>
                                <x-field name="name" label="Company Name" :value="old('name', $tenant->name)" required />
                                @error('name')
                                    <p id="name-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <x-field name="email" label="Email" type="email" :value="old('email', $tenant->email)" required />
                                @error('email')
                                    <p id="email-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <x-field name="phone" label="Phone" :value="old('phone', $tenant->phone)" />
                                @error('phone')
                                    <p id="phone-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Website -->
                            <div>
                                <x-field name="website" label="Website" type="url" :value="old('website', $tenant->website)" placeholder="https://example.com" />
                                @error('website')
                                    <p id="website-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Address</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Address -->
                            <div class="md:col-span-2">
                                <x-field name="address" label="Street Address" type="textarea" :value="old('address', $tenant->address)" rows="2" />
                                @error('address')
                                    <p id="address-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- City -->
                            <div>
                                <x-field name="city" label="City" :value="old('city', $tenant->city)" />
                                @error('city')
                                    <p id="city-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- State -->
                            <div>
                                <x-searchable-select
                                    name="state"
                                    label="State / Province"
                                    :options="$states->pluck('name')->toArray()"
                                    :value="old('state', $tenant->state ?? '')"
                                    placeholder="Select State"
                                    search-placeholder="Search states..."
                                    :has-error="$errors->has('state')" />
                                @error('state')
                                    <p id="state-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Country -->
                            <div>
                                <x-searchable-select
                                    name="country"
                                    label="Country"
                                    :options="$countries->pluck('name')->toArray()"
                                    :value="old('country', $tenant->country ?? '')"
                                    placeholder="Select Country"
                                    search-placeholder="Search countries..."
                                    :has-error="$errors->has('country')" />
                                @error('country')
                                    <p id="country-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Postal Code -->
                            <div>
                                <x-field name="postal_code" label="Postal Code" :value="old('postal_code', $tenant->postal_code)" />
                                @error('postal_code')
                                    <p id="postal_code-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Financial Settings</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Tax Number -->
                            <div>
                                <x-field name="tax_number" label="TIN (Tax Identification Number)" help="Printed on your invoices and sent to NRS with e-invoices. Digits, hyphens allowed." :value="old('tax_number', $tenant->tax_number)" />
                                @error('tax_number')
                                    <p id="tax_number-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Currency -->
                            <div>
                                <label for="currency" class="form-label">Currency</label>
                                <select name="currency" id="currency"
                                    class="form-control @error('currency') border-red-500 @enderror" @error('currency') aria-invalid="true" aria-describedby="currency-error" @enderror>
                                    <option value="NGN" {{ old('currency', $tenant->currency) == 'NGN' ? 'selected' : '' }}>NGN - Naira</option>
                                    <option value="USD" {{ old('currency', $tenant->currency) == 'USD' ? 'selected' : '' }}>USD - US Dollar</option>
                                    <option value="EUR" {{ old('currency', $tenant->currency) == 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                                    <option value="GBP" {{ old('currency', $tenant->currency) == 'GBP' ? 'selected' : '' }}>GBP - British Pound</option>
                                    <option value="CAD" {{ old('currency', $tenant->currency) == 'CAD' ? 'selected' : '' }}>CAD - Canadian Dollar</option>
                                    <option value="AUD" {{ old('currency', $tenant->currency) == 'AUD' ? 'selected' : '' }}>AUD - Australian Dollar</option>
                                    <option value="INR" {{ old('currency', $tenant->currency) == 'INR' ? 'selected' : '' }}>INR - Indian Rupee</option>
                                    <option value="JPY" {{ old('currency', $tenant->currency) == 'JPY' ? 'selected' : '' }}>JPY - Japanese Yen</option>
                                    <option value="CNY" {{ old('currency', $tenant->currency) == 'CNY' ? 'selected' : '' }}>CNY - Chinese Yuan</option>
                                </select>
                                @error('currency')
                                    <p id="currency-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Fiscal Year Start -->
                            <div>
                                <x-field name="fiscal_year_start" label="Fiscal Year Start" type="date" :value="old('fiscal_year_start', $tenant->fiscal_year_start?->format('Y-m-d'))" />
                                @error('fiscal_year_start')
                                    <p id="fiscal_year_start-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Company Logo</h3>
                        
                        <div class="flex items-start gap-6">
                            @if($tenant->logo)
                                <div class="flex-shrink-0">
                                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="Company Logo" class="h-24 w-24 object-contain rounded-lg border border-gray-200 dark:border-gray-700">
                                </div>
                            @endif
                            <div class="flex-1">
                                <label for="logo" class="form-label">Upload New Logo</label>
                                <input type="file" name="logo" id="logo" accept="image/*"
                                    class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 dark:file:bg-brand-900 file:text-brand-700 dark:file:text-brand-300 hover:file:bg-brand-100 dark:hover:file:bg-brand-800" @error('logo') aria-invalid="true" aria-describedby="logo-error" @enderror>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PNG, JPG, GIF up to 2MB</p>
                                @error('logo')
                                    <p id="logo-error" class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            @if($tenant->isOwnedBy(auth()->user()))
                {{-- Owner only (O7) --}}
                <div class="mt-6 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Close organisation</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @if($tenant->isClosing())
                                This business's data will be erased on {{ $tenant->closure_purge_at?->format('j F Y') }}.
                            @else
                                Close this business and erase its data after {{ \App\Models\Tenant::CLOSURE_GRACE_DAYS }} days.
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('settings.close-organisation') }}" class="text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400">Manage</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
