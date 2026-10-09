<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Create Export') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Export specific data in your preferred format</p>
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
            <form action="{{ route('exports.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Settings
                        </h3>

                        <div class="space-y-6">
                            <!-- Export Type -->
                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Data to Export <span class="text-red-600 dark:text-red-300">*</span></label>
                                <select name="type" id="type" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 @error('type') border-red-500 @enderror" @error('type') aria-invalid="true" aria-describedby="type-error" @enderror>
                                    @foreach($exportTypes as $value => $label)
                                        @if($value !== 'full_backup')
                                            <option value="{{ $value }}" {{ old('type', $type) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('type')
                                    <p id="type-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Format -->
                            <div>
                                <label for="format" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Export Format <span class="text-red-600 dark:text-red-300">*</span></label>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    @foreach($formats as $value => $label)
                                        <label class="relative flex items-center justify-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <input type="radio" name="format" value="{{ $value }}" class="sr-only peer" {{ old('format', 'csv') == $value ? 'checked' : '' }} @error('format') aria-invalid="true" aria-describedby="format-error" @enderror>
                                            <div class="text-center peer-checked:text-brand-600 dark:peer-checked:text-brand-300">
                                                <div class="text-2xl mb-1">
                                                    @if($value === 'csv')
                                                        📄
                                                    @elseif($value === 'xlsx')
                                                        📊
                                                    @elseif($value === 'json')
                                                        📋
                                                    @elseif($value === 'pdf')
                                                        📕
                                                    @endif
                                                </div>
                                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ strtoupper($value) }}</div>
                                            </div>
                                            <div class="absolute inset-0 border-2 border-transparent rounded-lg peer-checked:border-brand-500"></div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('format')
                                    <p id="format-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Date Range (Optional) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date Range (Optional)</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Filter data by date for invoices, bills, expenses, payroll, and journals.</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="date_from" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">From</label>
                                        <input type="date" name="date_from" id="date_from" value="{{ old('date_from') }}"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('date_from') aria-invalid="true" aria-describedby="date_from-error" @enderror>
                                    </div>
                                    <div>
                                        <label for="date_to" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">To</label>
                                        <input type="date" name="date_to" id="date_to" value="{{ old('date_to') }}"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" @error('date_to') aria-invalid="true" aria-describedby="date_to-error" @enderror>
                                    </div>
                                </div>
                                @error('date_from')
                                    <p id="date_from-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                                @error('date_to')
                                    <p id="date_to-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-brand-50 dark:bg-brand-900/30 border border-brand-200 dark:border-brand-800 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-brand-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-brand-700 dark:text-brand-200">
                                <strong>Note:</strong> Exports are available for download for 7 days after creation. For a complete backup of all your data, use the <a href="{{ route('exports.backup') }}" class="underline">Full Backup</a> option.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('exports.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Export Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
