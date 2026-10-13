{{-- Imports (tables plan T5): bring records in from a file, and what came of each import. --}}
@php
    $tone = ['pending' => 'pending', 'validating' => 'processing', 'mapping' => 'pending', 'processing' => 'processing', 'completed' => 'completed', 'failed' => 'failed'];
    $label = ['mapping' => 'Match columns', 'validating' => 'Checking', 'completed' => 'Done'];
    $quick = ['customers', 'vendors', 'items', 'chart_of_accounts', 'employees'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Imports" description="Bring customers, items, opening balances and more in from a CSV, Excel or JSON file.">
            <x-slot name="more">
                @foreach ($importTypes as $type => $name)
                    <x-table.menu-item :href="route('imports.template', ['type' => $type, 'format' => 'csv'])">Sample file: {{ $name }}</x-table.menu-item>
                @endforeach
            </x-slot>
            <x-slot name="actions">
                <a href="{{ route('imports.create') }}" class="btn-new">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                    New import
                </a>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        <nav class="flex flex-wrap items-center gap-2" aria-label="Start an import">
            <span class="text-sm text-gray-600 dark:text-gray-400">Import:</span>
            @foreach ($quick as $type)
                @isset($importTypes[$type])
                    <a href="{{ route('imports.create', ['type' => $type]) }}" class="tbl-chip h-8">{{ $importTypes[$type] }}</a>
                @endisset
            @endforeach
        </nav>

        @if ($imports->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No imports yet" text="Pick what to import above, or download a sample file from More to see the columns we expect.">
                    <a href="{{ route('imports.create') }}" class="btn-new">New import</a>
                </x-table.empty>
            </div>
        @else
            <x-table caption="Imports" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>File</x-table.th>
                    <x-table.th>Records</x-table.th>
                    <x-table.th>Date</x-table.th>
                    <x-table.th num>Rows</x-table.th>
                    <x-table.th num>Failed</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($imports as $import)
                    @php
                        $open = $import->status === 'mapping' ? route('imports.mapping', $import) : route('imports.show', $import);
                        $done = $import->status === 'completed';
                    @endphp
                    <tr>
                        <td class="max-w-[18rem]">
                            <a href="{{ $open }}" class="tbl-link block truncate" title="{{ $import->original_filename }}">{{ $import->original_filename }}</a>
                            <div class="text-xs tbl-muted">{{ strtoupper($import->format) }}</div>
                        </td>
                        <td>{{ $importTypes[$import->type] ?? \Illuminate\Support\Str::headline($import->type) }}</td>
                        <td class="tbl-muted whitespace-nowrap">{{ $import->created_at->format('j M Y, H:i') }}</td>
                        <td class="num {{ $import->total_rows ? '' : 'tbl-zero' }}">
                            @if ($done || ! $import->total_rows)
                                {{ $import->total_rows ? number_format($import->successful_rows).' of '.number_format($import->total_rows) : '—' }}
                            @else
                                {{ number_format($import->processed_rows) }} of {{ number_format($import->total_rows) }}
                            @endif
                        </td>
                        <td class="num {{ $import->failed_rows ? 'tbl-late' : 'tbl-zero' }}">{{ $import->failed_rows ? number_format($import->failed_rows) : '—' }}</td>
                        <td><x-status-badge :status="$tone[$import->status] ?? 'pending'" :label="$label[$import->status] ?? $import->getStatusLabel()" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$import->original_filename">
                                @if ($import->status === 'mapping')
                                    <x-table.menu-item :href="route('imports.mapping', $import)">Match columns</x-table.menu-item>
                                @else
                                    <x-table.menu-item :href="route('imports.show', $import)">View</x-table.menu-item>
                                @endif
                                @if ($import->canRetry())
                                    <x-table.menu-item :post="route('imports.retry', $import)">Try again</x-table.menu-item>
                                @endif
                                <x-table.menu-item :post="route('imports.destroy', $import)" method="DELETE" confirm="Delete this import? Records already brought in stay." danger>Delete</x-table.menu-item>
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Imports">
                @foreach ($imports as $import)
                    <li>
                        <x-table.card :href="$import->status === 'mapping' ? route('imports.mapping', $import) : route('imports.show', $import)"
                            :title="$importTypes[$import->type] ?? \Illuminate\Support\Str::headline($import->type)"
                            :meta="$import->created_at->format('j M Y').($import->total_rows ? ' · '.number_format($import->successful_rows).' of '.number_format($import->total_rows).' rows' : '')"
                            :tone="$import->failed_rows ? 'bad' : 'muted'">
                            <x-slot name="badge"><x-status-badge :status="$tone[$import->status] ?? 'pending'" :label="$label[$import->status] ?? $import->getStatusLabel()" /></x-slot>
                            @if ($import->failed_rows)
                                <x-slot name="alert">{{ number_format($import->failed_rows) }} rows failed</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-table.footer :rows="$imports" links />
    </div>
</x-app-layout>
