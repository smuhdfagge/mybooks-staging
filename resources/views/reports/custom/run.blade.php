{{-- A saved custom report, run (tables plan T6). --}}
@php
    $columnConfigs = $sourceConfig['columns'] ?? [];
    $columnLabels = collect($columnConfigs)->mapWithKeys(fn ($config, $key) => [$key => $config['label'] ?? $key])->toArray();
    $operators = \App\Models\CustomReport::getFilterOperators();
    $dataSources = \App\Models\CustomReport::getDataSources();
    $type = fn ($column) => $columnConfigs[$column]['type'] ?? '';
    $numeric = fn ($column) => in_array($type($column), ['decimal', 'integer'], true);
    $grouped = $customReport->group_by && $groupedData;
    $period = $customReport->date_field ? \Carbon\Carbon::parse($startDate)->format('j M Y').' to '.\Carbon\Carbon::parse($endDate)->format('j M Y') : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header :title="$customReport->name" :description="trim(($customReport->description ? rtrim($customReport->description, '.').'. ' : '').'From '.($dataSources[$customReport->data_source]['label'] ?? $customReport->data_source).'. Amounts in ₦.')">
            <x-slot name="downloads">
                @if ($data->count() > 0)
                    <button type="button" role="menuitem" data-call="exportTable" data-arg="csv" class="flex w-full items-center rounded-md px-2.5 py-1.5 text-left text-sm text-gray-800 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700">CSV file</button>
                @endif
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.custom.index')">Custom reports</x-table.menu-item>
                @if (auth()->id() === $customReport->created_by)
                    <x-table.menu-item :href="route('reports.custom.edit', $customReport)">Edit this report</x-table.menu-item>
                @endif
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet :title="$customReport->name" :period="$period">
        @if ($customReport->date_field)
            <x-report.filters :action="route('reports.custom.run', $customReport)">
                <x-report.date name="start_date" label="From" :value="$startDate" />
                <x-report.date name="end_date" label="To" :value="$endDate" />
            </x-report.filters>
        @endif

        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ number_format($data->count()) }} {{ \Illuminate\Support\Str::plural('record', $data->count()) }}@if ($grouped), grouped by {{ $columnLabels[$customReport->group_by] ?? $customReport->group_by }}@endif.
            @foreach ($customReport->filters ?? [] as $filter)
                @if ($loop->first) Only where @endif
                {{ $columnLabels[$filter['column']] ?? $filter['column'] }} {{ strtolower($operators[$filter['operator']] ?? $filter['operator']) }}@if (! in_array($filter['operator'], ['is_null', 'is_not_null'])) "{{ $filter['value'] }}"@endif{{ $loop->last ? '.' : ';' }}
            @endforeach
        </p>
        @if ($truncated ?? false)
            <x-report.check :ok="false">Only the first {{ number_format($maxRows) }} records are shown, and totals cover only these. Narrow the dates or add a filter to see the rest.</x-report.check>
        @endif

        @if (count($totals) > 0)
            <x-report.stats :cols="min(4, max(2, count($totals)))">
                @foreach ($totals as $key => $value)
                    @php
                        $parts = explode('_', $key);
                        $func = array_pop($parts);
                        $col = implode('_', $parts);
                    @endphp
                    <x-report.stat :label="ucfirst($func).' of '.strtolower($columnLabels[$col] ?? $col)" :value="is_numeric($value) ? number_format($value, 2) : $value" />
                @endforeach
            </x-report.stats>
        @endif

        @if ($data->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No records" :text="$customReport->date_field ? 'Try other dates, or change the report\'s filters.' : 'Nothing matches the report\'s filters.'" /></div>
        @else
            <x-table :caption="$customReport->name" id="reportTable">
                <x-slot name="head">
                    @if ($grouped)
                        <x-table.th>{{ $columnLabels[$customReport->group_by] ?? $customReport->group_by }}</x-table.th>
                        <x-table.th num>Records</x-table.th>
                        @foreach ($aggregatedData ? ($customReport->aggregations ?? []) : [] as $agg)
                            <x-table.th num>{{ ucfirst($agg['function']) }} of {{ strtolower($columnLabels[$agg['column']] ?? $agg['column']) }}</x-table.th>
                        @endforeach
                    @else
                        @foreach ($customReport->columns as $column)
                            <x-table.th :num="$numeric($column)">{{ $columnLabels[$column] ?? $column }}</x-table.th>
                        @endforeach
                    @endif
                </x-slot>
                @if ($grouped)
                    @foreach ($groupedData as $group => $items)
                        <tr>
                            <td class="rpt-wrap">{{ $group ?: '—' }}</td>
                            <td class="num">{{ number_format(count($items)) }}</td>
                            @foreach ($aggregatedData[$group] ?? [] as $value)
                                <td class="num">{{ is_numeric($value) ? number_format($value, 2) : $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @else
                    @foreach ($data as $row)
                        <tr>
                            @foreach ($customReport->columns as $column)
                                @php $value = data_get($row, $column); $t = $type($column); @endphp
                                <td class="{{ $numeric($column) ? 'num' : '' }} {{ is_null($value) ? 'tbl-zero' : '' }} {{ $t === 'date' || $t === 'datetime' ? 'tbl-muted' : '' }}">
                                    @if (is_null($value))
                                        —
                                    @elseif ($t === 'decimal')
                                        {{ number_format((float) $value, 2) }}
                                    @elseif ($t === 'date')
                                        {{ $value instanceof \Carbon\Carbon ? $value->format('j M Y') : $value }}
                                    @elseif ($t === 'datetime')
                                        {{ $value instanceof \Carbon\Carbon ? $value->format('j M Y H:i') : $value }}
                                    @elseif ($t === 'boolean')
                                        {{ $value ? 'Yes' : 'No' }}
                                    @elseif ($t === 'string' && $column === 'status')
                                        <x-status-badge :status="(string) $value" />
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endif
            </x-table>
        @endif
    </x-report.sheet>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function exportTable(format) {
            const table = document.querySelector('#reportTable table');
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
</x-app-layout>
