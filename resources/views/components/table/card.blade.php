{{--
    One row as a card on a phone (tables plan T1). The whole card opens the record.
    <x-table.card :href="…" :title="$customer" :amount="…" :meta="'INV-1 · 10 Oct 2026'">
        <x-slot name="badge"><x-status-badge … /></x-slot>
        <x-slot name="alert">Due 11 Sep · ₦88,580 to pay</x-slot>
    </x-table.card>
--}}
@props(['href', 'title', 'amount' => null, 'meta' => null, 'tone' => 'muted'])
<a href="{{ $href }}" class="block rounded-lg border border-gray-200 bg-white px-3.5 py-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
    <span class="flex items-baseline justify-between gap-3">
        <span class="min-w-0 truncate font-semibold text-gray-900 dark:text-white">{{ $title }}</span>
        @if ($amount !== null)<span class="whitespace-nowrap font-semibold tabular-nums text-gray-900 dark:text-white">{{ $amount }}</span>@endif
    </span>
    <span class="mt-1 flex items-center justify-between gap-3 text-sm">
        <span class="min-w-0 truncate text-gray-600 dark:text-gray-400">{{ $meta }}</span>
        {{ $badge ?? '' }}
    </span>
    @isset($alert)
        <span class="mt-1 block text-[13px] {{ $tone === 'bad' ? 'font-medium text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-400' }}">{{ $alert }}</span>
    @endisset
</a>
