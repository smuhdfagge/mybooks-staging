{{--
    Search and filters for a plain (non-Livewire) page (tables plan T5): the
    same look as <x-table.toolbar>, sent as an ordinary GET form. Selects
    send the form as soon as they change.

    <x-table.query-bar :action="route('admin.users.index')" placeholder="Search name or email" :filtered="…">
        <x-slot name="filters"> <x-table.query-select name="tenant" label="Business" :options="…" /> </x-slot>
    </x-table.query-bar>

    Keep other query values (a tab) with keep="['status']".
--}}
@props(['action', 'search' => 'search', 'placeholder' => 'Search', 'filtered' => false, 'keep' => []])
<form method="GET" action="{{ $action }}" x-data="{ more: false }" class="flex flex-wrap items-center gap-2" role="search">
    @foreach ($keep as $name)
        @if (request()->filled($name))<input type="hidden" name="{{ $name }}" value="{{ request($name) }}">@endif
    @endforeach
    <label class="relative min-w-0 flex-1 basis-56 md:max-w-sm">
        <span class="sr-only">{{ $placeholder }}</span>
        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 104.4 8.8l3.15 3.15a1 1 0 001.4-1.4l-3.14-3.16A5.5 5.5 0 009 3.5zM5.5 9a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z" clip-rule="evenodd"/></svg>
        <input type="search" name="{{ $search }}" value="{{ request($search) }}" placeholder="{{ $placeholder }}"
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
    <button type="submit" class="tbl-chip h-9">Search</button>
    @if ($filtered)
        <a href="{{ $action }}" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Clear</a>
    @endif
    <span class="hidden flex-1 md:block"></span>
    {{ $end ?? '' }}
</form>
