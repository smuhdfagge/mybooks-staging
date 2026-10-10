{{--
    Nothing to show (tables plan T1). Two kinds:
    - nothing yet:     <x-table.empty title="No invoices yet" text="…"> <a class="btn-primary">New invoice</a> </x-table.empty>
    - nothing matches: <x-table.empty filtered title="No invoices match these filters" />
--}}
@props(['title', 'text' => null, 'filtered' => false])
<div class="px-4 py-12 text-center">
    <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300" aria-hidden="true">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $filtered ? 'M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z' : 'M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z' }}"/></svg>
    </div>
    <p class="font-semibold text-gray-900 dark:text-white">{{ $title }}</p>
    <p class="mx-auto mt-1 max-w-sm text-sm text-gray-600 dark:text-gray-400">{{ $text ?? ($filtered ? 'Try another search, date or status.' : '') }}</p>
    <div class="mt-4 flex justify-center gap-2">
        @if ($filtered)
            <button type="button" wire:click="clearFilters" class="btn-secondary">Clear filters</button>
        @endif
        {{ $slot }}
    </div>
</div>
