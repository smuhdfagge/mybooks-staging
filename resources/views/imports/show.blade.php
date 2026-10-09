<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Import Results') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Review the results of your import</p>
            </div>
            <a href="{{ route('imports.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Imports
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Status Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ \App\Models\Import::getImportTypes()[$import->type] ?? ucwords(str_replace('_', ' ', $import->type)) }} Import
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $import->original_filename }}</p>
                        </div>
                        @php
                            $statusColors = [
                                'pending' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                'validating' => 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-300',
                                'mapping' => 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-300',
                                'processing' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$import->status] ?? $statusColors['pending'] }}">
                            @if($import->status === 'completed')
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            @elseif($import->status === 'failed')
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            @endif
                            {{ $import->getStatusLabel() }}
                        </span>
                    </div>

                    <!-- Statistics -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($import->total_rows) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Rows</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($import->processed_rows) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Processed</p>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($import->successful_rows) }}</p>
                            <p class="text-sm text-green-600 dark:text-green-400">Successful</p>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/30 rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($import->failed_rows) }}</p>
                            <p class="text-sm text-red-600 dark:text-red-400">Failed</p>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/30 rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($import->skipped_rows) }}</p>
                            <p class="text-sm text-yellow-600 dark:text-yellow-400">Skipped</p>
                        </div>
                    </div>

                    @if(in_array($import->status, ['pending', 'processing']))
                        {{-- Runs on the queue (P3): reload until it finishes. --}}
                        <p class="mt-6 text-sm text-gray-600 dark:text-gray-400" x-data x-init="setTimeout(() => window.location.reload(), 5000)">
                            The import is running in the background. This page refreshes by itself until it finishes.
                        </p>
                    @endif

                    <!-- Progress Bar -->
                    @if($import->isInProgress())
                        <div class="mt-6">
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Progress</span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $import->getProgressPercentage() }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-3">
                                <div class="bg-brand-600 h-3 rounded-full transition-all duration-300" style="width: {{ $import->getProgressPercentage() }}%"></div>
                            </div>
                        </div>
                    @endif

                    <!-- Timestamps -->
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Created</p>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $import->created_at->format('M d, Y H:i') }}</p>
                        </div>
                        @if($import->started_at)
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Started</p>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $import->started_at->format('M d, Y H:i') }}</p>
                        </div>
                        @endif
                        @if($import->completed_at)
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Completed</p>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $import->completed_at->format('M d, Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Duration</p>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $import->started_at->diffForHumans($import->completed_at, true) }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            @if($import->error_message)
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg p-4 mb-6">
                    <div class="flex">
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Import Error</h3>
                            <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $import->error_message }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Detailed Errors -->
            @if(!empty($import->errors))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            <svg class="w-5 h-5 inline mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            Errors ({{ count($import->errors) }})
                        </h3>
                        <div class="max-h-64 overflow-y-auto">
                            <ul class="space-y-2">
                                @foreach(array_slice($import->errors, 0, 50) as $error)
                                    <li class="text-sm text-red-600 dark:text-red-400 flex items-start">
                                        <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $error }}
                                    </li>
                                @endforeach
                            </ul>
                            @if(count($import->errors) > 50)
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                    ... and {{ count($import->errors) - 50 }} more errors
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Warnings -->
            @if(!empty($import->warnings))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            <svg class="w-5 h-5 inline mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            Warnings ({{ count($import->warnings) }})
                        </h3>
                        <div class="max-h-48 overflow-y-auto">
                            <ul class="space-y-2">
                                @foreach(array_slice($import->warnings, 0, 20) as $warning)
                                    <li class="text-sm text-yellow-600 dark:text-yellow-400 flex items-start">
                                        <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $warning }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="flex justify-between">
                <div class="flex gap-3">
                    @if($import->canRetry())
                        <form action="{{ route('imports.retry', $import) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Retry Import
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('imports.create', ['type' => $import->type]) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        New Import
                    </a>
                </div>
                <form action="{{ route('imports.destroy', $import) }}" method="POST" data-confirm="Are you sure you want to delete this import record?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
