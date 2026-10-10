{{-- A one-line verdict on a report (tables plan T6): <x-report.check :ok="$balanced">Debits equal credits.</x-report.check> --}}
@props(['ok' => true])
<p {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-lg border px-3.5 py-2.5 text-sm '.($ok
    ? 'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200'
    : 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200')]) }} role="status">
    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        @if ($ok)
            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/>
        @else
            <path fill-rule="evenodd" d="M8.3 3.2a2 2 0 013.4 0l6.1 10.6A2 2 0 0116.1 17H3.9a2 2 0 01-1.7-3.2L8.3 3.2zM10 7a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1zm0 7.5a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
        @endif
    </svg>
    <span>{{ $slot }}</span>
</p>
