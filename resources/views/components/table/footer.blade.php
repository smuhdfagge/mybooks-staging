{{--
    Under a list: "1–25 of 2,160", rows per page, pages (tables plan T1).
    $rows: the paginator. Rows per page sets the Livewire property perPage.
    On a plain (non-Livewire) page pass links: pages become ordinary links
    and there is no rows-per-page picker.
--}}
@props(['rows', 'sizes' => [10, 25, 50, 100], 'links' => false])
@php
    $total = $rows->total();
    $first = $rows->firstItem() ?? 0;
    $last = $rows->lastItem() ?? 0;
    $page = $rows->currentPage();
    $lastPage = $rows->lastPage();
    $window = collect([1, $lastPage, $page - 1, $page, $page + 1])->filter(fn ($p) => $p >= 1 && $p <= $lastPage)->unique()->sort()->values();
    $btn = 'inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md border px-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500';
    $off = 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700';
@endphp
@if ($total > 0)
    <nav class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm" aria-label="Pages">
        <p class="text-gray-600 dark:text-gray-400 tabular-nums">{{ number_format($first) }}–{{ number_format($last) }} of {{ number_format($total) }}</p>
        <span class="flex-1"></span>
        @unless ($links)
        <label class="hidden items-center gap-2 text-gray-600 dark:text-gray-400 sm:flex">
            Rows per page
            <select wire:model.live="perPage" class="h-8 rounded-md border-gray-300 py-0 pl-2 pr-7 text-sm text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                @foreach ($sizes as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
            </select>
        </label>
        @endunless
        @if ($lastPage > 1)
            <div class="flex items-center gap-1">
                @if ($links)
                    <a href="{{ $page > 1 ? $rows->url($page - 1) : '#' }}" @if ($page <= 1) aria-disabled="true" @endif class="{{ $btn }} {{ $off }} {{ $page <= 1 ? 'pointer-events-none opacity-40' : '' }}" aria-label="Previous page">‹</a>
                @else
                <button type="button" wire:click="previousPage('{{ $rows->getPageName() }}')" @disabled($page <= 1) class="{{ $btn }} {{ $off }}" aria-label="Previous page">‹</button>
                @endif
                @php $prev = 0; @endphp
                @foreach ($window as $p)
                    @if ($p - $prev > 1)<span class="hidden px-1 text-gray-500 dark:text-gray-400 sm:inline" aria-hidden="true">…</span>@endif
                    @php $cls = $btn.' '.($p === $page ? 'border-brand-600 bg-brand-600 text-white' : $off).' '.($p === $page || $p === 1 || $p === $lastPage ? '' : 'hidden sm:inline-flex'); @endphp
                    @if ($links)
                        <a href="{{ $rows->url($p) }}" @if ($p === $page) aria-current="page" @endif class="{{ $cls }}">{{ number_format($p) }}</a>
                    @else
                    <button type="button" wire:click="gotoPage({{ $p }}, '{{ $rows->getPageName() }}')" @if ($p === $page) aria-current="page" @endif class="{{ $cls }}">{{ number_format($p) }}</button>
                    @endif
                    @php $prev = $p; @endphp
                @endforeach
                @if ($links)
                    <a href="{{ $page < $lastPage ? $rows->url($page + 1) : '#' }}" @if ($page >= $lastPage) aria-disabled="true" @endif class="{{ $btn }} {{ $off }} {{ $page >= $lastPage ? 'pointer-events-none opacity-40' : '' }}" aria-label="Next page">›</a>
                @else
                <button type="button" wire:click="nextPage('{{ $rows->getPageName() }}')" @disabled($page >= $lastPage) class="{{ $btn }} {{ $off }}" aria-label="Next page">›</button>
                @endif
            </div>
        @endif
    </nav>
@endif
