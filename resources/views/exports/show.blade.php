<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Export') }}: {{ ucwords(str_replace('_', ' ', $export->type)) }}
            </h2>
            <a href="{{ route('exports.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">Back to Exports</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="mt-1"><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $export->status_badge_class }}">{{ ucfirst($export->status) }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Format</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ strtoupper((string) $export->format) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">File</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100 break-all">{{ $export->filename ?: '—' }} @if($export->file_size)({{ $export->formatted_file_size }})@endif</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Requested by</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $export->user?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $export->created_at?->format('M j, Y g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Available until</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            @if($export->expires_at)
                                {{ $export->expires_at->format('M j, Y g:i A') }}@if($export->isExpired()) <span class="text-red-600 dark:text-red-400">(expired)</span>@endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    @if($export->included_data)
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Included</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ collect($export->included_data)->map(fn ($t) => ucwords(str_replace('_', ' ', $t)))->implode(', ') }}</dd>
                        </div>
                    @endif
                    @if($export->error_message)
                        <div class="sm:col-span-2 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 text-sm text-red-800 dark:text-red-200">
                            {{ $export->error_message }}
                        </div>
                    @endif
                </dl>

                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-3">
                    @if($export->isDownloadable() && ! $export->isExpired())
                        <a href="{{ route('exports.download', $export) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition ease-in-out duration-150">Download</a>
                    @endif
                    <form action="{{ route('exports.destroy', $export) }}" method="POST" onsubmit="return confirm('Delete this export?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-red-300 dark:border-red-600 rounded-md font-semibold text-xs text-red-600 dark:text-red-400 uppercase tracking-widest hover:bg-red-50 dark:hover:bg-red-900/20 transition ease-in-out duration-150">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
