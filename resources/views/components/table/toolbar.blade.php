{{--
    Search and filters in one row (tables plan T1). When rows are ticked the
    row becomes the bulk bar: "3 selected", the actions, Clear selection.
    On a phone the filters fold behind a "Filters" button.

    <x-table.toolbar search="search" placeholder="Search number or customer" :selected="count($selectedItems)">
        <x-slot name="filters"> …small selects… </x-slot>
        <x-slot name="end"> …Export… </x-slot>
        <x-slot name="bulk"> <x-table.bulk-button action="delete" danger confirm="…">Delete</x-table.bulk-button> </x-slot>
    </x-table.toolbar>
--}}
@props(['search' => 'search', 'placeholder' => 'Search', 'selected' => 0, 'filtered' => false])
<div x-data="{ more: false }">
@if ($selected > 0 && isset($bulk))
    <div class="flex min-h-[2.75rem] flex-wrap items-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 dark:border-brand-700 dark:bg-brand-900/40" role="region" aria-label="Actions for ticked rows">
        <span class="mr-1 text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($selected) }} selected</span>
        {{ $bulk }}
        <span class="flex-1"></span>
        <button type="button" wire:click="clearSelection" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Clear selection</button>
    </div>
@else
    <div class="flex flex-wrap items-center gap-2">
        <label class="relative min-w-0 flex-1 basis-56 md:max-w-sm">
            <span class="sr-only">{{ $placeholder }}</span>
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 104.4 8.8l3.15 3.15a1 1 0 001.4-1.4l-3.14-3.16A5.5 5.5 0 009 3.5zM5.5 9a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z" clip-rule="evenodd"/></svg>
            <input type="search" wire:model.live.debounce.300ms="{{ $search }}" placeholder="{{ $placeholder }}"
                class="h-9 w-full rounded-md border-gray-300 pl-8 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        </label>
        @isset($filters)
            <button type="button" x-on:click="more = !more" :aria-expanded="more.toString()" class="tbl-chip h-9 md:hidden">
                Filters @if ($filtered)<span class="h-2 w-2 rounded-full bg-brand-600 dark:bg-brand-300" aria-label="(on)"></span>@endif
            </button>
            <div class="w-full flex-wrap items-center gap-2 md:flex md:w-auto" :class="more ? 'flex' : 'hidden md:flex'">
                {{ $filters }}
            </div>
        @endisset
        <span class="hidden flex-1 md:block"></span>
        {{ $end ?? '' }}
    </div>
@endif
</div>
