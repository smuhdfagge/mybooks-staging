{{-- A dashboard card: title, optional subtitle and link, then the content. --}}
@props(['title', 'subtitle' => null, 'link' => null, 'linkText' => null, 'id' => null])
<section {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800']) }} @if ($id) aria-labelledby="{{ $id }}" @endif>
    <div class="mb-3 flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
        <div class="min-w-0 flex-1 basis-40">
            <h2 @if ($id) id="{{ $id }}" @endif class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
            @if ($subtitle)<p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">{{ $subtitle }}</p>@endif
        </div>
        @isset($actions)
            <div class="flex-none">{{ $actions }}</div>
        @elseif ($link)
            <a href="{{ $link }}" class="flex-none text-sm font-semibold text-brand-700 hover:text-brand-900 hover:underline dark:text-brand-300 dark:hover:text-brand-200">{{ $linkText }}</a>
        @endisset
    </div>
    {{ $slot }}
</section>
