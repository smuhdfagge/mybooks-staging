{{--
    Under a list: "1–25 of 2,160", rows per page, pages (tables plan T1).
    $rows: the paginator. Rows per page sets the Livewire property perPage.
--}}
@props(['rows', 'sizes' => [10, 25, 50, 100]])
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
        <label class="hidden items-center gap-2 text-gray-600 dark:text-gray-400 sm:flex">
            Rows per page
            <select wire:model.live="perPage" class="h-8 rounded-md border-gray-300 py-0 pl-2 pr-7 text-sm text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                @foreach ($sizes as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
            </select>
        </label>
        @if ($lastPage > 1)
            <div class="flex items-center gap-1">
                <button type="button" wire:click="previousPage('{{ $rows->getPageName() }}')" @disabled($page <= 1) class="{{ $btn }} {{ $off }}" aria-label="Previous page">‹</button>
                @php $prev = 0; @endphp
                @foreach ($window as $p)
                    @if ($p - $prev > 1)<span class="hidden px-1 text-gray-500 dark:text-gray-400 sm:inline" aria-hidden="true">…</span>@endif
                    <button type="button" wire:click="gotoPage({{ $p }}, '{{ $rows->getPageName() }}')" @if ($p === $page) aria-current="page" @endif
                        class="{{ $btn }} {{ $p === $page ? 'border-brand-600 bg-brand-600 text-white' : $off }} {{ $p === $page || $p === 1 || $p === $lastPage ? '' : 'hidden sm:inline-flex' }}">{{ number_format($p) }}</button>
                    @php $prev = $p; @endphp
                @endforeach
                <button type="button" wire:click="nextPage('{{ $rows->getPageName() }}')" @disabled($page >= $lastPage) class="{{ $btn }} {{ $off }}" aria-label="Next page">›</button>
            </div>
        @endif
    </nav>
@endif
