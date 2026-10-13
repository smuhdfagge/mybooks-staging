{{--
    Top of a list page: title, one line about the list, main button, and
    "More" for everything else (tables plan T1).

    <x-table.page-header title="Invoices" description="…">
        <x-slot name="more"> <x-table.menu-item href="…">Export</x-table.menu-item> </x-slot>
        <x-slot name="actions"> <a class="btn-primary" …>New invoice</a> </x-slot>
    </x-table.page-header>

    On a record page, a badge slot sits next to the title (its status).
    title and description can also be slots, for text with markup.
--}}
@props(['title' => null, 'description' => null])
<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h2 class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xl font-semibold leading-tight text-gray-900 dark:text-white">
            <span>{{ $title }}</span>
            @isset($badge){{ $badge }}@endisset
        </h2>
        @if ($description)<p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>@endif
    </div>
    <div class="flex items-center gap-2">
        @isset($more)
            <x-table.dropdown label="More" align="right">{{ $more }}</x-table.dropdown>
        @endisset
        {{ $actions ?? '' }}
    </div>
</div>
