<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Full Backup') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Create a complete backup of all your business data</p>
            </div>
            <a href="{{ route('exports.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('exports.backup.process') }}" method="POST" class="space-y-6">
                @csrf

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Backup Settings
                        </h3>

                        <div class="space-y-6">
                            <!-- Format -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Backup Format <span class="text-red-600 dark:text-red-300">*</span></label>
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach($backupFormats as $value => $label)
                                        <label class="relative flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <input type="radio" name="format" value="{{ $value }}" class="mt-1 peer" {{ old('format', 'zip') == $value ? 'checked' : '' }} @error('format') aria-invalid="true" aria-describedby="format-error" @enderror>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    @if($value === 'zip')
                                                        📦 ZIP Archive
                                                    @else
                                                        📋 JSON File
                                                    @endif
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $label }}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('format')
                                    <p id="format-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Data to Include -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Data to Include <span class="text-red-600 dark:text-red-300">*</span></label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Select which data to include in your backup.</p>
                                
                                <div class="space-y-2">
                                    <label class="flex items-center mb-3 pb-3 border-b border-gray-200 dark:border-gray-700">
                                        <input type="checkbox" id="select_all" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-brand-600 shadow-sm focus:ring-brand-500 dark:text-brand-300">
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Select All</span>
                                    </label>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach($dataTypes as $value => $label)
                                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer">
                                                <input type="checkbox" name="included_data[]" value="{{ $value }}" 
                                                    class="data-checkbox rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-brand-600 shadow-sm focus:ring-brand-500 dark:text-brand-300"
                                                    {{ in_array($value, old('included_data', array_keys($dataTypes))) ? 'checked' : '' }}>
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                @error('included_data')
                                    <p id="included_data-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning Box -->
                <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Important</h3>
                            <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                Large backups may take several minutes to generate. Backup files are available for download for 7 days. We recommend downloading and storing backups in a secure location.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('exports.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Create Backup
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        document.getElementById('select_all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.data-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Update "Select All" state based on individual checkboxes
        document.querySelectorAll('.data-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.data-checkbox');
                const allChecked = Array.from(allCheckboxes).every(c => c.checked);
                document.getElementById('select_all').checked = allChecked;
            });
        });

        // Initialize "Select All" state on page load
        window.addEventListener('DOMContentLoaded', function() {
            const allCheckboxes = document.querySelectorAll('.data-checkbox');
            const allChecked = Array.from(allCheckboxes).every(c => c.checked);
            document.getElementById('select_all').checked = allChecked;
        });
    </script>
    @endpush
</x-app-layout>
