<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Import Data') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Upload a file to import data into your account</p>
            </div>
            <a href="{{ route('imports.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('imports.upload') }}" method="POST" enctype="multipart/form-data" class="p-6">
                    @csrf

                    <!-- Step indicator -->
                    <div class="mb-8">
                        <div class="flex items-center justify-center">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-brand-600 rounded-full">
                                    <span class="text-white font-semibold">1</span>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">Upload File</span>
                            </div>
                            <div class="w-24 h-1 mx-4 bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full">
                                    <span class="text-gray-500 dark:text-gray-400 font-semibold">2</span>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-500 dark:text-gray-400">Map Columns</span>
                            </div>
                            <div class="w-24 h-1 mx-4 bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full">
                                    <span class="text-gray-500 dark:text-gray-400 font-semibold">3</span>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-500 dark:text-gray-400">Import</span>
                            </div>
                        </div>
                    </div>

                    <!-- Import Type -->
                    <div class="mb-6">
                        <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            What would you like to import? <span class="text-red-600 dark:text-red-300">*</span>
                        </label>
                        <select name="type" id="type" required
                                class="form-control">
                            <option value="">Select import type...</option>
                            @foreach($importTypes as $value => $label)
                                <option value="{{ $value }}" {{ ($type ?? old('type')) === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- File Upload -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Upload File <span class="text-red-600 dark:text-red-300">*</span>
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg hover:border-brand-500 dark:hover:border-brand-500 transition" 
                             x-data="{ dragging: false, fileName: '' }"
                             @dragover.prevent="dragging = true"
                             @dragleave.prevent="dragging = false"
                             @drop.prevent="dragging = false; fileName = $event.dataTransfer.files[0]?.name || ''; $refs.fileInput.files = $event.dataTransfer.files"
                             :class="{ 'border-brand-500 bg-brand-50 dark:bg-brand-900/20': dragging }">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                    <label for="file" class="relative cursor-pointer rounded-md font-medium text-brand-600 dark:text-brand-300 hover:text-brand-500 focus-within:outline-none">
                                        <span>Upload a file</span>
                                        <input id="file" name="file" type="file" class="sr-only" required
                                               accept="{{ collect(\App\Models\Import::acceptedExtensions())->map(fn ($e) => '.'.$e)->implode(',') }}"
                                               x-ref="fileInput"
                                               @change="fileName = $event.target.files[0]?.name || ''">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \App\Models\Import::excelSupported() ? 'CSV, Excel (.xlsx), or JSON' : 'CSV or JSON' }} up to 10MB
                                </p>
                                <p x-show="fileName" x-text="'Selected: ' + fileName" class="text-sm font-medium text-brand-600 dark:text-brand-300 mt-2"></p>
                            </div>
                        </div>
                    </div>

                    <!-- File Format Tips -->
                    <div class="mb-6 p-4 bg-brand-50 dark:bg-brand-900/20 border border-brand-200 dark:border-brand-800 rounded-lg">
                        <h4 class="text-sm font-medium text-brand-800 dark:text-brand-300 mb-2">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            File Format Tips
                        </h4>
                        <ul class="text-sm text-brand-700 dark:text-brand-300 space-y-1">
                            <li>• The first row should contain column headers</li>
                            <li>• You'll be able to map columns to fields in the next step</li>
                            <li>• Download a sample template to see the expected format</li>
                            <li>• Dates should be in YYYY-MM-DD, MM/DD/YYYY, or DD/MM/YYYY format</li>
                        </ul>
                    </div>

                    <!-- Download Template Link -->
                    <div class="mb-6" x-data="{ selectedType: '{{ $type ?? '' }}' }">
                        <select name="type" id="type" x-model="selectedType" 
                                @change="document.getElementById('type').value = selectedType"
                                class="hidden">
                        </select>
                        <template x-if="selectedType">
                            <a :href="'{{ route('imports.template') }}?type=' + selectedType + '&format=csv'" 
                               class="inline-flex items-center text-sm text-brand-600 dark:text-brand-300 hover:text-brand-800 dark:hover:text-brand-300">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Download sample template for selected type
                            </a>
                        </template>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('imports.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                            Continue to Mapping
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
