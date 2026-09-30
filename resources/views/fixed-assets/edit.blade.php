<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Fixed Asset') }}
            </h2>
            <a href="{{ route('fixed-assets.show', $asset) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Cancel
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('fixed-assets.update', $asset) }}">
                        @csrf
                        @method('PUT')

                        <!-- Basic Information -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium mb-2">Asset Name *</label>
                                    <input type="text" name="name" id="name" value="{{ old('name', $asset->name) }}" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                                    @error('name')
                                        <p id="name-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="asset_number" class="block text-sm font-medium mb-2">Asset Number *</label>
                                    <input type="text" name="asset_number" id="asset_number" value="{{ old('asset_number', $asset->asset_number) }}" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('asset_number') aria-invalid="true" aria-describedby="asset_number-error" @enderror>
                                    @error('asset_number')
                                        <p id="asset_number-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="category_id" class="block text-sm font-medium mb-2">Category *</label>
                                    <select name="category_id" id="category_id" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('category_id') aria-invalid="true" aria-describedby="category_id-error" @enderror>
                                        <option value="">Select Category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ old('category_id', $asset->category_id) == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <p id="category_id-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="status" class="block text-sm font-medium mb-2">Status *</label>
                                    <select name="status" id="status" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('status') aria-invalid="true" aria-describedby="status-error" @enderror>
                                        <option value="active" {{ old('status', $asset->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="under_maintenance" {{ old('status', $asset->status) == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                                        <option value="idle" {{ old('status', $asset->status) == 'idle' ? 'selected' : '' }}>Idle</option>
                                        @if(in_array($asset->status, ['disposed', 'sold', 'fully_depreciated'], true))
                                            <option value="{{ $asset->status }}" selected>{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</option>
                                        @endif
                                    </select>
                                    @error('status')
                                        <p id="status-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="description" class="block text-sm font-medium mb-2">Description</label>
                                    <textarea name="description" id="description" rows="3"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $asset->description) }}</textarea>
                                    @error('description')
                                        <p id="description-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Location & Assignment -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Location & Assignment</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="location" class="block text-sm font-medium mb-2">Location</label>
                                    <input type="text" name="location" id="location" value="{{ old('location', $asset->location) }}"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('location') aria-invalid="true" aria-describedby="location-error" @enderror>
                                    @error('location')
                                        <p id="location-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="assigned_to" class="block text-sm font-medium mb-2">Assigned To (User ID)</label>
                                    <input type="number" name="assigned_to" id="assigned_to" value="{{ old('assigned_to', $asset->assigned_to) }}"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('assigned_to') aria-invalid="true" aria-describedby="assigned_to-error" @enderror>
                                    @error('assigned_to')
                                        <p id="assigned_to-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="notes" class="block text-sm font-medium mb-2">Notes</label>
                                    <textarea name="notes" id="notes" rows="3"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ old('notes', $asset->notes) }}</textarea>
                                    @error('notes')
                                        <p id="notes-error" class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('fixed-assets.show', $asset) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Update Asset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
