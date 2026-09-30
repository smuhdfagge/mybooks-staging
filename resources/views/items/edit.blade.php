<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Item') }}: {{ $item->name }}
            </h2>
            <a href="{{ route('items.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('items.update', $item) }}" method="POST" class="p-6" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Basic Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Basic Information</h3>

                        <!-- Image Upload -->
                        <div class="mb-6" x-data="{ preview: null }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item Image</label>
                            <div class="flex items-center gap-4">
                                <div class="h-24 w-24 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-700">
                                    @if($item->image_path)
                                        <img x-show="!preview" src="{{ asset('storage/' . $item->image_path) }}" class="h-24 w-24 rounded-lg object-cover" alt="{{ $item->name }}">
                                    @else
                                        <svg x-show="!preview" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    @endif
                                    <img x-show="preview" :src="preview" class="h-24 w-24 rounded-lg object-cover" alt="Image preview" x-cloak>
                                </div>
                                <div>
                                    <label for="item-image-upload" class="cursor-pointer inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                        {{ $item->image_path ? 'Change Image' : 'Upload Image' }}
                                    </label>
                                    <input type="file" name="image" accept="image/*" class="hidden" id="item-image-upload"
                                        @change="const file = $event.target.files[0]; if(file) { const reader = new FileReader(); reader.onload = e => preview = e.target.result; reader.readAsDataURL(file); }">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG, GIF or WebP. Max 2MB.</p>
                                    @error('image') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Item Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" value="{{ old('name', $item->name) }}" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sku" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SKU / Item Code</label>
                                <input type="text" name="sku" id="sku" value="{{ old('sku', $item->sku) }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('sku') border-red-500 @enderror">
                                @error('sku')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                                <select name="category_id" id="category_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('category_id') border-red-500 @enderror">
                                    <option value="">-- No Category --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $item->category_id) == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type <span class="text-red-500">*</span></label>
                                <select name="type" id="type" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('type') border-red-500 @enderror">
                                    <option value="product" {{ old('type', $item->type) == 'product' ? 'selected' : '' }}>Product</option>
                                    <option value="service" {{ old('type', $item->type) == 'service' ? 'selected' : '' }}>Service</option>
                                </select>
                                @error('type')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="unit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit of Measure</label>
                                <input type="text" name="unit" id="unit" value="{{ old('unit', $item->unit) }}" placeholder="e.g., pcs, kg, hrs"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('unit') border-red-500 @enderror">
                                @error('unit')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea name="description" id="description" rows="3"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror">{{ old('description', $item->description) }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Pricing</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="selling_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Selling Price <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">@currencySymbol</span>
                                    <input type="number" name="selling_price" id="selling_price" value="{{ old('selling_price', $item->selling_price) }}" min="0" step="0.01" required
                                        class="w-full pl-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('selling_price') border-red-500 @enderror">
                                </div>
                                @error('selling_price')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="cost_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cost Price</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">@currencySymbol</span>
                                    <input type="number" name="cost_price" id="cost_price" value="{{ old('cost_price', $item->cost_price) }}" min="0" step="0.01"
                                        class="w-full pl-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('cost_price') border-red-500 @enderror">
                                </div>
                                @error('cost_price')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tax_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tax Rate (%)</label>
                                <div class="relative">
                                    <input type="number" name="tax_rate" id="tax_rate" value="{{ old('tax_rate', $item->tax_rate) }}" min="0" max="100" step="0.01"
                                        class="w-full pr-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('tax_rate') border-red-500 @enderror">
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 dark:text-gray-400">%</span>
                                </div>
                                @error('tax_rate')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Settings -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Inventory Settings</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center">
                                <input type="checkbox" name="track_inventory" id="track_inventory" value="1" {{ old('track_inventory', $item->track_inventory) ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:checked:bg-indigo-600">
                                <label for="track_inventory" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Track inventory for this item</label>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" name="is_taxable" id="is_taxable" value="1" {{ old('is_taxable', $item->is_taxable) ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:checked:bg-indigo-600">
                                <label for="is_taxable" class="ml-2 text-sm text-gray-700 dark:text-gray-300">This item is taxable</label>
                            </div>

                            <div>
                                <label for="reorder_level" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reorder Level</label>
                                <input type="number" name="reorder_level" id="reorder_level" value="{{ old('reorder_level', $item->reorder_level) }}" min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('reorder_level') border-red-500 @enderror">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Alert when stock falls below this level</p>
                                @error('reorder_level')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="is_active" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select name="is_active" id="is_active"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="1" {{ old('is_active', $item->is_active) == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('is_active', $item->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Current Inventory Info -->
                    @if($item->track_inventory && $item->inventory)
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Current Inventory</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Quantity on Hand</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $item->inventory->quantity ?? 0 }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Reserved</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $item->inventory->reserved_quantity ?? 0 }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Available</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ ($item->inventory->quantity ?? 0) - ($item->inventory->reserved_quantity ?? 0) }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('items.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
