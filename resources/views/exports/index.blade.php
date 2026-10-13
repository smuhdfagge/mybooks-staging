{{-- Exports (tables plan T5): files of your records to download, and full backups. --}}
@php
    $tone = ['pending' => 'pending', 'processing' => 'processing', 'completed' => 'completed', 'failed' => 'failed'];
    $label = ['pending' => 'Waiting', 'processing' => 'Preparing', 'completed' => 'Ready', 'failed' => 'Failed'];
    $quick = ['customers' => 'Customers', 'vendors' => 'Vendors', 'items' => 'Items', 'invoices' => 'Invoices', 'expenses' => 'Expenses'];
    $busy = $exports->whereIn('status', ['pending', 'processing'])->isNotEmpty();
    $expired = $exports->filter(fn ($e) => $e->isExpired())->isNotEmpty();
    $kind = fn ($e) => $exportTypes[$e->type] ?? \Illuminate\Support\Str::headline($e->type);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Exports" description="Download your records as a file, or make a full backup. Files are kept for a few days.">
            <x-slot name="more">
                <x-table.menu-item :href="route('exports.backup')">Full backup</x-table.menu-item>
                @if ($expired)
                    <x-table.menu-item :post="route('exports.cleanup')">Remove expired files</x-table.menu-item>
                @endif
                @can('view settings')<x-table.menu-item :href="route('activity-logs.index')">Activity log</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                <a href="{{ route('exports.create') }}" class="btn-new">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                    New export
                </a>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-600 dark:text-gray-400">Quick CSV:</span>
            @foreach ($quick as $type => $name)
                <form action="{{ route('exports.quick') }}" method="POST">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="format" value="csv">
                    <button type="submit" class="tbl-chip h-8">{{ $name }}</button>
                </form>
            @endforeach
        </div>

        @if ($busy)
            {{-- Built on the queue (P3): reload until ready. --}}
            <p class="text-sm text-gray-600 dark:text-gray-400" role="status" x-data x-init="setTimeout(() => window.location.reload(), 5000)">Preparing your export. This page refreshes by itself.</p>
        @endif

        @if ($exports->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No exports yet" text="Pick a quick CSV above, or start a new export to choose the records, dates and file type.">
                    <a href="{{ route('exports.create') }}" class="btn-new">New export</a>
                </x-table.empty>
            </div>
        @else
            <x-table caption="Exports" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Records</x-table.th>
                    <x-table.th>File</x-table.th>
                    <x-table.th>Made</x-table.th>
                    <x-table.th>Kept until</x-table.th>
                    <x-table.th num>Size</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($exports as $export)
                    @php $ready = $export->isDownloadable() && ! $export->isExpired(); @endphp
                    <tr>
                        <td>
                            @if ($ready)
                                <a href="{{ route('exports.download', $export) }}" class="tbl-link">{{ $kind($export) }}</a>
                            @else
                                {{ $kind($export) }}
                            @endif
                            @if ($export->type === 'full_backup' && $export->included_data)
                                <div class="text-xs tbl-muted">{{ count($export->included_data) }} kinds of record</div>
                            @endif
                        </td>
                        <td class="tbl-muted">{{ strtoupper($export->format) }}</td>
                        <td class="tbl-muted whitespace-nowrap">{{ $export->created_at->format('j M Y, H:i') }}</td>
                        <td class="whitespace-nowrap {{ $export->isExpired() ? 'tbl-late' : ($export->expires_at ? 'tbl-muted' : 'tbl-zero') }}">
                            {{ $export->expires_at ? ($export->isExpired() ? 'Expired' : $export->expires_at->format('j M Y')) : '—' }}
                        </td>
                        <td class="num {{ $export->file_size ? 'tbl-muted' : 'tbl-zero' }}">{{ $export->file_size ? $export->formatted_file_size : '—' }}</td>
                        <td><x-status-badge :status="$tone[$export->status] ?? 'pending'" :label="$label[$export->status] ?? ucfirst($export->status)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$kind($export).' export'">
                                @if ($ready)<x-table.menu-item :href="route('exports.download', $export)">Download</x-table.menu-item>@endif
                                <x-table.menu-item :href="route('exports.show', $export)">Details</x-table.menu-item>
                                <x-table.menu-item :post="route('exports.destroy', $export)" method="DELETE" confirm="Delete this export file?" danger>Delete</x-table.menu-item>
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Exports">
                @foreach ($exports as $export)
                    @php $ready = $export->isDownloadable() && ! $export->isExpired(); @endphp
                    <li>
                        <x-table.card :href="$ready ? route('exports.download', $export) : route('exports.show', $export)" :title="$kind($export)"
                            :meta="strtoupper($export->format).' · '.$export->created_at->format('j M Y')">
                            <x-slot name="badge"><x-status-badge :status="$export->isExpired() ? 'expired' : ($tone[$export->status] ?? 'pending')" :label="$export->isExpired() ? 'Expired' : ($label[$export->status] ?? ucfirst($export->status))" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-table.footer :rows="$exports" links />
    </div>
</x-app-layout>
