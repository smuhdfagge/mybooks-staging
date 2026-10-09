<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('item-categories.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Item Category') }}
            </h2>
        </div>
    </x-slot>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <form action="{{ route('item-categories.update', $itemCategory) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <x-input-label for="name" :value="__('Category Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $itemCategory->name)" required placeholder="e.g., Electronics, Services, Furniture" />
                        <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- Parent Category -->
                    <div>
                        <x-searchable-select
                            name="parent_id"
                            label="Parent Category (Optional)"
                            :options="$parentCategories->pluck('name', 'id')->toArray()"
                            :value="old('parent_id', (string)($itemCategory->parent_id ?? ''))"
                            placeholder="None (Top Level Category)"
                            search-placeholder="Search categories..."
                            :has-error="$errors->has('parent_id')" />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Select a parent to create a subcategory</p>
                        <x-input-error id="parent_id-error" :messages="$errors->get('parent_id')" class="mt-2" />
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <x-input-label for="description" :value="__('Description')" />
                    <textarea name="description" id="description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm" placeholder="Optional description for this category" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $itemCategory->description) }}</textarea>
                    <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                </div>

                <!-- Status -->
                <div class="mt-6">
                    <label class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $itemCategory->is_active) ? 'checked' : '' }} class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-brand-600 shadow-sm focus:ring-brand-500 dark:focus:ring-brand-600 dark:text-brand-300">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                    </label>
                </div>

                <div class="mt-8 flex justify-end gap-4">
                    <a href="{{ route('item-categories.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
