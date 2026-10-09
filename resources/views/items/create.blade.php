<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Add New Item') }}
            </h2>
            <a href="{{ route('items.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
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
                <form action="{{ route('items.store') }}" method="POST" class="p-6" enctype="multipart/form-data">
                    @csrf

                    <!-- Basic Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Basic Information
                        </h3>

                        <!-- Image Upload -->
                        <div class="mb-6" x-data="{ preview: null }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item Image</label>
                            <div class="flex items-center gap-4">
                                <div class="h-24 w-24 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-700">
                                    <img x-show="preview" :src="preview" class="h-24 w-24 rounded-lg object-cover" alt="Image preview" x-cloak>
                                    <svg x-show="!preview" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <label for="item-image-upload" class="cursor-pointer inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                        Upload Image
                                    </label>
                                    <input type="file" name="image" accept="image/*" class="hidden" id="item-image-upload"
                                        @change="const file = $event.target.files[0]; if(file) { const reader = new FileReader(); reader.onload = e => preview = e.target.result; reader.readAsDataURL(file); }" @error('image') aria-invalid="true" aria-describedby="image-error" @enderror>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG, GIF or WebP. Max 2MB.</p>
                                    @error('image') <p id="image-error" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <x-field name="name" label="Item Name" :value="old('name')" required />
                            </div>

                            <div>
                                <x-field name="sku" label="SKU / Item Code" :value="old('sku')" />
                            </div>

                            <div>
                                <label for="category_id" class="form-label">Category</label>
                                <select name="category_id" id="category_id"
                                    class="form-control @error('category_id') border-red-500 @enderror" @error('category_id') aria-invalid="true" aria-describedby="category_id-error" @enderror>
                                    <option value="">-- No Category --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <p id="category_id-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="type" class="form-label">Type <span class="text-red-500">*</span></label>
                                <select name="type" id="type" required
                                    class="form-control @error('type') border-red-500 @enderror" @error('type') aria-invalid="true" aria-describedby="type-error" @enderror>
                                    <option value="product" {{ old('type', 'product') == 'product' ? 'selected' : '' }}>Product</option>
                                    <option value="service" {{ old('type') == 'service' ? 'selected' : '' }}>Service</option>
                                </select>
                                @error('type')
                                    <p id="type-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-field name="unit" label="Unit of Measure" :value="old('unit')" placeholder="e.g., pcs, kg, hrs" />
                            </div>

                            <div class="md:col-span-2">
                                <x-field name="description" label="Description" type="textarea" :value="old('description')" rows="3" />
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Pricing
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="selling_price" class="form-label">Selling Price <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="number" name="selling_price" id="selling_price" value="{{ old('selling_price', '0.00') }}" min="0" step="0.01" required
                                        class="form-control @error('selling_price') border-red-500 @enderror" @error('selling_price') aria-invalid="true" aria-describedby="selling_price-error" @enderror>
                                </div>
                                @error('selling_price')
                                    <p id="selling_price-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="cost_price" class="form-label">Cost Price</label>
                                <div class="relative">
                                    <input type="number" name="cost_price" id="cost_price" value="{{ old('cost_price', '0.00') }}" min="0" step="0.01"
                                        class="form-control @error('cost_price') border-red-500 @enderror" @error('cost_price') aria-invalid="true" aria-describedby="cost_price-error" @enderror>
                                </div>
                                @error('cost_price')
                                    <p id="cost_price-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                <div class="relative">
                                    <input type="number" name="tax_rate" id="tax_rate" value="{{ old('tax_rate', '0') }}" min="0" max="100" step="0.01"
                                        class="w-full pr-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 @error('tax_rate') border-red-500 @enderror" @error('tax_rate') aria-invalid="true" aria-describedby="tax_rate-error" @enderror>
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 dark:text-gray-400">%</span>
                                </div>
                                @error('tax_rate')
                                    <p id="tax_rate-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Settings -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Inventory Settings
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center">
                                <input type="checkbox" name="track_inventory" id="track_inventory" value="1" {{ old('track_inventory', true) ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:checked:bg-brand-600 dark:text-brand-300">
                                <label for="track_inventory" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Track inventory for this item</label>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" name="is_taxable" id="is_taxable" value="1" {{ old('is_taxable') ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:checked:bg-brand-600 dark:text-brand-300">
                                <label for="is_taxable" class="ml-2 text-sm text-gray-700 dark:text-gray-300">This item is taxable</label>
                            </div>

                            <div>
                                <x-field name="reorder_level" label="Reorder Level" type="number" :value="old('reorder_level', 0)" min="0" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Alert when stock falls below this level</p>
                                @error('reorder_level')
                                    <p id="reorder_level-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('items.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <x-primary-button class="px-4">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Item
                        </x-primary-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
