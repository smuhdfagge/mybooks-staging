<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Bulk Journal Update') }}
            </h2>
            <a href="{{ route('journals.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Journals
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">


            <!-- Instructions Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Bulk Journal Operations</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Use this page to perform bulk operations on journal entries. You can post multiple draft journals at once,
                                export journal data, or import journal entries from a CSV file.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Bulk Post Journals -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 rounded-full p-3">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h3 class="ml-3 text-lg font-medium text-gray-900 dark:text-gray-100">Bulk Post Journals</h3>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Post multiple draft journal entries at once. Only balanced journals can be posted.
                        </p>
                        <form action="{{ route('journals.bulk-update') }}" method="POST" id="bulkPostForm">
                            @csrf
                            <input type="hidden" name="action" value="post">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Journals to Post</label>
                                <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2">
                                    @php
                                        $draftJournals = \App\Models\Journal::where('is_posted', false)->orderBy('journal_date', 'desc')->get();
                                    @endphp
                                    @forelse($draftJournals as $journal)
                                        <label class="flex items-center p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                            <input type="checkbox" name="journal_ids[]" value="{{ $journal->id }}"
                                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $journal->journal_number }} - {{ $journal->journal_date->format('M d, Y') }}
                                                <span class="text-gray-500 dark:text-gray-400">({{ $journal->description ? Str::limit($journal->description, 30) : 'No description' }})</span>
                                            </span>
                                        </label>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400 p-2">No draft journals available.</p>
                                    @endforelse
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition"
                                {{ $draftJournals->isEmpty() ? 'disabled' : '' }}>
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Post Selected Journals
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Export Journals -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900/50 rounded-full p-3">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h3 class="ml-3 text-lg font-medium text-gray-900 dark:text-gray-100">Export Journals</h3>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Export journal entries to CSV or Excel format for external reporting or backup.
                        </p>
                        <form action="{{ route('journals.bulk-update') }}" method="GET" id="exportForm">
                            <input type="hidden" name="action" value="export">
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <x-field name="start_date" label="Start Date" type="date" />
                                </div>
                                <div>
                                    <x-field name="end_date" label="End Date" type="date" />
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="export_format" class="form-label">Export Format</label>
                                <select name="format" id="export_format"
                                    class="form-control">
                                    <option value="csv">CSV</option>
                                    <option value="xlsx">Excel (XLSX)</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Export Journals
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Import Journals -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900/50 rounded-full p-3">
                                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                            </div>
                            <h3 class="ml-3 text-lg font-medium text-gray-900 dark:text-gray-100">Import Journals</h3>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Import journal entries from a CSV file. Download the template to ensure proper formatting.
                        </p>
                        <form action="{{ route('journals.bulk-update') }}" method="POST" enctype="multipart/form-data" id="importForm">
                            @csrf
                            <input type="hidden" name="action" value="import">
                            
                            <div class="mb-4">
                                <label for="import_file" class="form-label">Select CSV File</label>
                                <input type="file" name="file" id="import_file" accept=".csv,.xlsx"
                                    class="w-full text-sm text-gray-500 dark:text-gray-400
                                        file:mr-4 file:py-2 file:px-4
                                        file:rounded-md file:border-0
                                        file:text-sm file:font-semibold
                                        file:bg-indigo-50 file:text-indigo-700
                                        dark:file:bg-indigo-900/50 dark:file:text-indigo-400
                                        hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900">
                            </div>
                            
                            <div class="flex gap-2">
                                <a href="#" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Download Template
                                </a>
                                <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                    Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bulk Delete -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 bg-red-100 dark:bg-red-900/50 rounded-full p-3">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </div>
                            <h3 class="ml-3 text-lg font-medium text-gray-900 dark:text-gray-100">Bulk Delete Drafts</h3>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Delete multiple draft journal entries at once. Posted journals cannot be deleted.
                        </p>
                        <form action="{{ route('journals.bulk-update') }}" method="POST" id="bulkDeleteForm" data-confirm="Are you sure you want to delete the selected journals? This action cannot be undone.">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="action" value="delete">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Journals to Delete</label>
                                <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2">
                                    @forelse($draftJournals as $journal)
                                        <label class="flex items-center p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                            <input type="checkbox" name="delete_journal_ids[]" value="{{ $journal->id }}"
                                                class="rounded border-gray-300 dark:border-gray-600 text-red-600 shadow-sm focus:border-red-500 focus:ring-red-500">
                                            <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $journal->journal_number }} - {{ $journal->journal_date->format('M d, Y') }}
                                                <span class="text-gray-500 dark:text-gray-400">({{ $journal->description ? Str::limit($journal->description, 30) : 'No description' }})</span>
                                            </span>
                                        </label>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400 p-2">No draft journals available.</p>
                                    @endforelse
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition"
                                {{ $draftJournals->isEmpty() ? 'disabled' : '' }}>
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete Selected Journals
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                @php
                    $totalJournals = \App\Models\Journal::count();
                    $postedJournals = \App\Models\Journal::where('is_posted', true)->count();
                    $draftCount = \App\Models\Journal::where('is_posted', false)->count();
                    $thisMonthJournals = \App\Models\Journal::whereMonth('journal_date', now()->month)->whereYear('journal_date', now()->year)->count();
                @endphp
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-900 rounded-full p-3">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Journals</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $totalJournals }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-full p-3">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Posted</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $postedJournals }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 rounded-full p-3">
                            <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Drafts</p>
                            <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $draftCount }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-full p-3">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">This Month</p>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $thisMonthJournals }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
