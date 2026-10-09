<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $customReport->name }}
                </h2>
                @if($customReport->description)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $customReport->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.custom.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back
                </a>
                @if(auth()->id() === $customReport->created_by)
                    <a href="{{ route('reports.custom.edit', $customReport) }}" class="inline-flex items-center justify-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @php
        $columnConfigs = $sourceConfig['columns'] ?? [];
        $columnLabels = collect($columnConfigs)->mapWithKeys(fn($config, $key) => [$key => $config['label'] ?? $key])->toArray();
        $operators = \App\Models\CustomReport::getFilterOperators();
        $dataSources = \App\Models\CustomReport::getDataSources();
    @endphp

    <div class="space-y-6">
        <!-- Date Range Filter (if report has date field) -->
        @if($customReport->date_field)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('reports.custom.run', $customReport) }}" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="{{ $startDate }}"
                                class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                                class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Refresh
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <!-- Report Info -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                        </svg>
                        <span>Data Source: <strong class="text-gray-700 dark:text-gray-300">{{ $dataSources[$customReport->data_source]['label'] ?? $customReport->data_source }}</strong></span>
                    </div>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <span>Total Records: <strong class="text-gray-700 dark:text-gray-300">{{ number_format($data->count()) }}</strong></span>
                    </div>
                    @if($truncated ?? false)
                        <div class="flex items-center text-amber-700 dark:text-amber-400">
                            <span>Only the first {{ number_format($maxRows) }} records are shown, and totals cover only these. Narrow the dates or add a filter to see the rest.</span>
                        </div>
                    @endif
                    @if($customReport->group_by)
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            <span>Grouped By: <strong class="text-gray-700 dark:text-gray-300">{{ $columnLabels[$customReport->group_by] ?? $customReport->group_by }}</strong></span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Aggregations Summary -->
        @if(count($totals) > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Summary</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        @foreach($totals as $key => $value)
                            @php
                                $parts = explode('_', $key);
                                $func = array_pop($parts);
                                $col = implode('_', $parts);
                                $label = ucfirst($func) . ' of ' . ($columnLabels[$col] ?? $col);
                            @endphp
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }}</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                                    @if(is_numeric($value))
                                        {{ number_format($value, 2) }}
                                    @else
                                        {{ $value }}
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Results Table -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Results</h3>
                    <!-- Export Buttons -->
                    <div class="flex gap-2">
                        <button type="button" data-call="exportTable" data-arg="csv" class="inline-flex items-center px-3 py-1.5 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 text-sm font-medium rounded hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Export CSV
                        </button>
                        <button type="button" data-print class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print
                        </button>
                    </div>
                </div>

                @if($data->count() > 0)
                    <div class="overflow-x-auto">
                        <table id="reportTable" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    @if($customReport->group_by && $groupedData)
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            {{ $columnLabels[$customReport->group_by] ?? $customReport->group_by }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Count
                                        </th>
                                        @if($aggregatedData)
                                            @foreach($customReport->aggregations ?? [] as $agg)
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    {{ ucfirst($agg['function']) }} of {{ $columnLabels[$agg['column']] ?? $agg['column'] }}
                                                </th>
                                            @endforeach
                                        @endif
                                    @else
                                        @foreach($customReport->columns as $column)
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">
                                                {{ $columnLabels[$column] ?? $column }}
                                            </th>
                                        @endforeach
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @if($customReport->group_by && $groupedData)
                                    @foreach($groupedData as $group => $items)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                                {{ $group ?: 'N/A' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                                {{ number_format(count($items)) }}
                                            </td>
                                            @if($aggregatedData && isset($aggregatedData[$group]))
                                                @foreach($aggregatedData[$group] as $value)
                                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">
                                                        @if(is_numeric($value))
                                                            {{ number_format($value, 2) }}
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </td>
                                                @endforeach
                                            @endif
                                        </tr>
                                    @endforeach
                                @else
                                    @foreach($data as $row)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            @foreach($customReport->columns as $column)
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200 whitespace-nowrap">
                                                    @php
                                                        $value = data_get($row, $column);
                                                        $colConfig = $columnConfigs[$column] ?? null;
                                                    @endphp
                                                    
                                                    @if(is_null($value))
                                                        <span class="text-gray-500 dark:text-gray-400">-</span>
                                                    @elseif($colConfig && ($colConfig['type'] ?? '') === 'decimal')
                                                        {{ number_format($value, 2) }}
                                                    @elseif($colConfig && ($colConfig['type'] ?? '') === 'date')
                                                        {{ $value instanceof \Carbon\Carbon ? $value->format('M d, Y') : $value }}
                                                    @elseif($colConfig && ($colConfig['type'] ?? '') === 'datetime')
                                                        {{ $value instanceof \Carbon\Carbon ? $value->format('M d, Y H:i') : $value }}
                                                    @elseif($colConfig && ($colConfig['type'] ?? '') === 'boolean')
                                                        @if($value)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Yes</span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">No</span>
                                                        @endif
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No results found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            @if($customReport->date_field)
                                Try adjusting the date range or filters.
                            @else
                                No data matches the specified filters.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Applied Filters -->
        @if(count($customReport->filters ?? []) > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Active Filters</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($customReport->filters as $filter)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-brand-100 dark:bg-brand-900 text-brand-800 dark:text-brand-200">
                                {{ $columnLabels[$filter['column']] ?? $filter['column'] }}
                                <span class="mx-1 text-brand-500 dark:text-brand-300">{{ $operators[$filter['operator']] ?? $filter['operator'] }}</span>
                                @if(!in_array($filter['operator'], ['is_null', 'is_not_null']))
                                    "{{ $filter['value'] }}"
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function exportTable(format) {
            const table = document.getElementById('reportTable');
            if (!table) return;

            const rows = table.querySelectorAll('tr');
            let csv = [];

            rows.forEach(row => {
                const cols = row.querySelectorAll('th, td');
                let rowData = [];
                cols.forEach(col => {
                    let text = col.innerText.trim();
                    // Same rule as App\Support\Csv: a cell starting with = + - @
                    // would run as a formula in Excel, unless it is a plain number (S4).
                    if (/^[=+\-@\t\r]/.test(text) && !/^[+-]?(\d{1,3}(,\d{3})+|\d*)(\.\d+)?$/.test(text)) {
                        text = "'" + text;
                    }
                    text = text.replace(/"/g, '""');
                    rowData.push('"' + text + '"');
                });
                csv.push(rowData.join(','));
            });

            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', '{{ Str::slug($customReport->name) }}_{{ date('Y-m-d') }}.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
    @endpush

    @push('styles')
    <style>
        @media print {
            .no-print, .no-print * {
                display: none !important;
            }
        }
    </style>
    @endpush
</x-app-layout>
