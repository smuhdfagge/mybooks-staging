<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Map Columns') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Match your file columns to the database fields</p>
            </div>
            <a href="{{ route('imports.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Cancel
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('imports.process', $import) }}" method="POST" class="p-6">
                    @csrf

                    <!-- Step indicator -->
                    <div class="mb-8">
                        <div class="flex items-center justify-center">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-green-500 rounded-full">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-500 dark:text-gray-400">Upload File</span>
                            </div>
                            <div class="w-24 h-1 mx-4 bg-indigo-600"></div>
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-indigo-600 rounded-full">
                                    <span class="text-white font-semibold">2</span>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">Map Columns</span>
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

                    <!-- File Info -->
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $import->original_filename }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ strtoupper($import->format) }} • {{ number_format($preview['total_rows']) }} rows detected
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                                {{ \App\Models\Import::getImportTypes()[$import->type] ?? $import->type }}
                            </span>
                        </div>
                    </div>

                    <!-- Column Mapping -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Column Mapping</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Match each column from your file to the corresponding field. Fields marked with <span class="text-red-500">*</span> are required.
                        </p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">File Column</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sample Data</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Maps To</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($preview['headers'] as $header)
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $header }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                                    @if(!empty($preview['rows']))
                                                        {{ $preview['rows'][0][$header] ?? '-' }}
                                                    @else
                                                        -
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <select name="mapping[{{ $header }}]" 
                                                        class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">-- Skip this column --</option>
                                                    @foreach($availableFields as $field => $label)
                                                        <option value="{{ $field }}" 
                                                                {{ ($suggestedMapping[$header] ?? '') === $field ? 'selected' : '' }}>
                                                            {{ $label }}
                                                            @if(in_array($field, $requiredFields)) * @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Data Preview -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Data Preview</h3>
                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">#</th>
                                        @foreach($preview['headers'] as $header)
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase truncate max-w-[150px]">
                                                {{ $header }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($preview['rows'] as $index => $row)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                            @foreach($preview['headers'] as $header)
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100 truncate max-w-[150px]">
                                                    {{ $row[$header] ?? '-' }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Showing first {{ count($preview['rows']) }} of {{ number_format($preview['total_rows']) }} rows
                        </p>
                    </div>

                    <!-- Import Options -->
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Import Options</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="options[skip_duplicates]" value="1" checked
                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Skip duplicate records</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="options[update_existing]" value="1"
                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Update existing records if found</span>
                            </label>
                        </div>
                    </div>

                    <!-- Required Fields Notice -->
                    <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                        <div class="flex">
                            <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Required Fields</h3>
                                <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">
                                    Make sure to map the following required fields: 
                                    <strong>{{ implode(', ', array_map(fn($f) => $availableFields[$f] ?? $f, $requiredFields)) }}</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-between">
                        <a href="{{ route('imports.create', ['type' => $import->type]) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
