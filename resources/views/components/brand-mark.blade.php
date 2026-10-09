{{--
    The MyBooks mark, Option A "Ledger total" (rebrand R2): three ledger
    lines and the double underline of a final total.

    <x-brand-mark class="h-8 w-8" />                    navy square, for light backgrounds
    <x-brand-mark variant="reversed" class="h-8 w-8" /> lines only, for the navy sidebar
    <x-brand-mark variant="mono" class="h-8 w-8" />     one colour (currentColor)

    Same drawing as public/images/brand/*.svg.
--}}
@props(['variant' => 'colour'])

@if ($variant === 'reversed')
    <svg {{ $attributes }} viewBox="0 0 64 64" fill="none" stroke-linecap="round" aria-hidden="true" focusable="false">
        <path d="M10 14H54M10 25H42M10 36H48" stroke="#FFFFFF" stroke-width="6" />
        <path d="M10 47H54M10 54H54" stroke="#D79E36" stroke-width="3.5" />
    </svg>
@elseif ($variant === 'mono')
    <svg {{ $attributes }} viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <rect width="64" height="64" rx="14" fill="currentColor" />
        <g fill="none" stroke-linecap="round" stroke="#FFFFFF">
            <path d="M17 17H47M17 26H39M17 35H43" stroke-width="5" />
            <path d="M17 45H47M17 51H47" stroke-width="3" />
        </g>
    </svg>
@else
    <svg {{ $attributes }} viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <rect width="64" height="64" rx="14" fill="#1F4E79" />
        <g fill="none" stroke-linecap="round">
            <path d="M17 17H47M17 26H39M17 35H43" stroke="#FFFFFF" stroke-width="5" />
            <path d="M17 45H47M17 51H47" stroke="#D79E36" stroke-width="3" />
        </g>
    </svg>
@endif
