<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Add Fixed Asset') }}
            </h2>
            <a href="{{ route('fixed-assets.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <form action="{{ route('fixed-assets.store') }}" method="POST">
                @csrf

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Asset Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asset Name *</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                                @error('name') <p id="name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                                <select name="category_id" id="category_id" data-call="applyCategoryDefaults"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('category_id') aria-invalid="true" aria-describedby="category_id-error" @enderror>
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" 
                                            data-method="{{ $category->depreciation_method }}"
                                            data-life="{{ $category->useful_life_years * 12 }}"
                                            data-salvage="{{ $category->salvage_value_percentage }}"
                                            {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id') <p id="category_id-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                <textarea name="description" id="description" rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
                                @error('description') <p id="description-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="serial_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Serial Number</label>
                                <input type="text" name="serial_number" id="serial_number" value="{{ old('serial_number') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('serial_number') aria-invalid="true" aria-describedby="serial_number-error" @enderror>
                                @error('serial_number') <p id="serial_number-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="model" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Model</label>
                                <input type="text" name="model" id="model" value="{{ old('model') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('model') aria-invalid="true" aria-describedby="model-error" @enderror>
                                @error('model') <p id="model-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="manufacturer" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Manufacturer</label>
                                <input type="text" name="manufacturer" id="manufacturer" value="{{ old('manufacturer') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('manufacturer') aria-invalid="true" aria-describedby="manufacturer-error" @enderror>
                                @error('manufacturer') <p id="manufacturer-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                                <input type="text" name="location" id="location" value="{{ old('location') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('location') aria-invalid="true" aria-describedby="location-error" @enderror>
                                @error('location') <p id="location-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Purchase Details</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="vendor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vendor</label>
                                <select name="vendor_id" id="vendor_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('vendor_id') aria-invalid="true" aria-describedby="vendor_id-error" @enderror>
                                    <option value="">Select Vendor</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                            {{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vendor_id') <p id="vendor_id-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="purchase_invoice" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice/Reference</label>
                                <input type="text" name="purchase_invoice" id="purchase_invoice" value="{{ old('purchase_invoice') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('purchase_invoice') aria-invalid="true" aria-describedby="purchase_invoice-error" @enderror>
                                @error('purchase_invoice') <p id="purchase_invoice-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="purchase_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Purchase Date *</label>
                                <input type="date" name="purchase_date" id="purchase_date" value="{{ old('purchase_date', date('Y-m-d')) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('purchase_date') aria-invalid="true" aria-describedby="purchase_date-error" @enderror>
                                @error('purchase_date') <p id="purchase_date-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="in_service_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">In Service Date *</label>
                                <input type="date" name="in_service_date" id="in_service_date" value="{{ old('in_service_date', date('Y-m-d')) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('in_service_date') aria-invalid="true" aria-describedby="in_service_date-error" @enderror>
                                @error('in_service_date') <p id="in_service_date-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="purchase_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Purchase Cost *</label>
                                <input type="number" name="purchase_cost" id="purchase_cost" value="{{ old('purchase_cost') }}" step="0.01" min="0" required data-call="calculateSalvage"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('purchase_cost') aria-invalid="true" aria-describedby="purchase_cost-error" @enderror>
                                @error('purchase_cost') <p id="purchase_cost-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="funding_source" class="block text-sm font-medium text-gray-700 dark:text-gray-300">How was it paid for? *</label>
                                <select name="funding_source" id="funding_source" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('funding_source') aria-invalid="true" aria-describedby="funding_source-error" @enderror>
                                    @foreach(\App\Models\FixedAsset::FUNDING_SOURCES as $value => $label)
                                        <option value="{{ $value }}" {{ old('funding_source', 'bank') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choose "vendor bill" if the purchase is already on a bill in MyBooks, so it isn't counted twice.</p>
                                @error('funding_source') <p id="funding_source-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="salvage_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Salvage Value *</label>
                                <input type="number" name="salvage_value" id="salvage_value" value="{{ old('salvage_value', 0) }}" step="0.01" min="0" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('salvage_value') aria-invalid="true" aria-describedby="salvage_value-error" @enderror>
                                @error('salvage_value') <p id="salvage_value-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Depreciation Settings</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="depreciation_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Depreciation Method *</label>
                                <select name="depreciation_method" id="depreciation_method" required data-call="toggleDepreciationRate"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('depreciation_method') aria-invalid="true" aria-describedby="depreciation_method-error" @enderror>
                                    @foreach($depreciationMethods as $method => $label)
                                        <option value="{{ $method }}" {{ old('depreciation_method', 'straight_line') == $method ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('depreciation_method') <p id="depreciation_method-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="useful_life_months" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Useful Life (Months) *</label>
                                <input type="number" name="useful_life_months" id="useful_life_months" value="{{ old('useful_life_months', 60) }}" min="1" max="600" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('useful_life_months') aria-invalid="true" aria-describedby="useful_life_months-error" @enderror>
                                <p class="mt-1 text-xs text-gray-500">e.g., 60 months = 5 years</p>
                                @error('useful_life_months') <p id="useful_life_months-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div id="depreciation_rate_group" style="display: none;">
                                <label for="depreciation_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Depreciation Rate (%)</label>
                                <input type="number" name="depreciation_rate" id="depreciation_rate" value="{{ old('depreciation_rate') }}" step="0.01" min="0" max="100"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('depreciation_rate') aria-invalid="true" aria-describedby="depreciation_rate-error" @enderror>
                                <p class="mt-1 text-xs text-gray-500">Leave empty for automatic calculation</p>
                                @error('depreciation_rate') <p id="depreciation_rate-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Accounting Settings</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="asset_account_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asset Account</label>
                                <select name="asset_account_id" id="asset_account_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('asset_account_id') aria-invalid="true" aria-describedby="asset_account_id-error" @enderror>
                                    <option value="">Use Default</option>
                                    @foreach($assetAccounts as $account)
                                        <option value="{{ $account->id }}" {{ old('asset_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->account_code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('asset_account_id') <p id="asset_account_id-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="depreciation_account_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Depreciation Expense Account</label>
                                <select name="depreciation_account_id" id="depreciation_account_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('depreciation_account_id') aria-invalid="true" aria-describedby="depreciation_account_id-error" @enderror>
                                    <option value="">Use Default</option>
                                    @foreach($expenseAccounts as $account)
                                        <option value="{{ $account->id }}" {{ old('depreciation_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->account_code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('depreciation_account_id') <p id="depreciation_account_id-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="accumulated_depreciation_account_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Accumulated Depreciation Account</label>
                                <select name="accumulated_depreciation_account_id" id="accumulated_depreciation_account_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('accumulated_depreciation_account_id') aria-invalid="true" aria-describedby="accumulated_depreciation_account_id-error" @enderror>
                                    <option value="">Use Default</option>
                                    @foreach($assetAccounts as $account)
                                        <option value="{{ $account->id }}" {{ old('accumulated_depreciation_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->account_code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('accumulated_depreciation_account_id') <p id="accumulated_depreciation_account_id-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                        <textarea name="notes" id="notes" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ old('notes') }}</textarea>
                        @error('notes') <p id="notes-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-4">
                    <a href="{{ route('fixed-assets.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-400 dark:hover:bg-gray-500">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700">
                        Create Asset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script nonce="{{ app('csp-nonce') }}">
        let savedSalvagePercentage = 0;

        function applyCategoryDefaults() {
            const select = document.getElementById('category_id');
            const option = select.options[select.selectedIndex];
            
            if (option.value) {
                const method = option.dataset.method;
                const life = option.dataset.life;
                const salvage = parseFloat(option.dataset.salvage);

                if (method) document.getElementById('depreciation_method').value = method;
                if (life) document.getElementById('useful_life_months').value = life;
                savedSalvagePercentage = salvage;
                calculateSalvage();
                toggleDepreciationRate();
            }
        }

        function calculateSalvage() {
            if (savedSalvagePercentage > 0) {
                const cost = parseFloat(document.getElementById('purchase_cost').value) || 0;
                const salvageValue = cost * (savedSalvagePercentage / 100);
                document.getElementById('salvage_value').value = salvageValue.toFixed(2);
            }
        }

        function toggleDepreciationRate() {
            const method = document.getElementById('depreciation_method').value;
            const rateGroup = document.getElementById('depreciation_rate_group');
            
            if (method === 'declining_balance' || method === 'double_declining') {
                rateGroup.style.display = 'block';
            } else {
                rateGroup.style.display = 'none';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleDepreciationRate();
        });
    </script>
</x-app-layout>
