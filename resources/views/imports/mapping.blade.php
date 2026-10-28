<x-app-layout>
    <x-slot name="header">
        <x-table.page-header description="Match your file columns to the database fields" title="Map columns" />
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
                            <div class="w-24 h-1 mx-4 bg-brand-600"></div>
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-brand-600 rounded-full">
                                    <span class="text-white font-semibold">2</span>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">Map Columns</span>
                            </div>
                            <div class="w-24 h-1 mx-4 bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full">
                                    <span class="text-gray-600 dark:text-gray-300 font-semibold">3</span>
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
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-300">
                                {{ \App\Models\Import::getImportTypes()[$import->type] ?? $import->type }}
                            </span>
                        </div>
                    </div>

                    <!-- Column Mapping -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Column Mapping</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Match each column from your file to the corresponding field. Fields marked with <span class="text-red-600 dark:text-red-300">*</span> are required.
                        </p>

                        <x-table caption="Column mapping">
<x-slot name="head">
                                        <x-table.th>File column</x-table.th>
                                        <x-table.th>Sample data</x-table.th>
                                        <x-table.th>Maps to</x-table.th>
</x-slot>

                                    @foreach($preview['headers'] as $header)
                                        <tr>
                                            <td>
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $header }}</span>
                                            </td>
                                            <td>
                                                <div class="text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                                    @if(!empty($preview['rows']))
                                                        {{ $preview['rows'][0][$header] ?? '—' }}
                                                    @else
                                                        -
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <select name="mapping[{{ $header }}]" 
                                                        class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
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
</x-table>
                    </div>

                    <!-- Data Preview -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Data Preview</h3>
                        <x-table caption="First rows of the file">
<x-slot name="head">
                                        <x-table.th>#</x-table.th>
                                        @foreach($preview['headers'] as $header)
                                            <x-table.th>{{ $header }}</x-table.th>
                                        @endforeach
</x-slot>

                                    @foreach($preview['rows'] as $index => $row)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            @foreach($preview['headers'] as $header)
                                                <td class="truncate max-w-[150px]">
                                                    {{ $row[$header] ?? '—' }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
</x-table>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Showing first {{ count($preview['rows']) }} of {{ number_format($preview['total_rows']) }} rows
                        </p>
                    </div>

                    <!-- Import Options -->
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Import Options</h3>
                        @php
                            // Invoices, bills and journals are never changed by an import: a
                            // duplicate is skipped or imported again, not updated.
                            $isDocument = in_array($import->type, [\App\Models\Import::TYPE_INVOICES, \App\Models\Import::TYPE_BILLS, \App\Models\Import::TYPE_JOURNALS], true);
                        @endphp
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="options[skip_duplicates]" value="1" checked
                                       class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:text-brand-300">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Skip duplicate records</span>
                            </label>
                            @if ($isDocument)
                                <p class="ml-6 text-xs text-gray-500 dark:text-gray-400">
                                    {{ match ($import->type) {
                                        \App\Models\Import::TYPE_INVOICES => 'An invoice already in your books for the same customer with the same invoice number is skipped.',
                                        \App\Models\Import::TYPE_BILLS => 'A bill already in your books from the same vendor with the same bill number is skipped.',
                                        default => 'A journal already in your books with the same reference, date and total is skipped.',
                                    } }}
                                    So you can import the file again once you've fixed anything it refused.
                                </p>
                            @else
                                <label class="flex items-center">
                                    <input type="checkbox" name="options[update_existing]" value="1"
                                           class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:text-brand-300">
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Update existing records if found</span>
                                </label>
                            @endif
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
                        <a href="{{ route('imports.create', ['type' => $import->type]) }}" class="btn-secondary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Start import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
