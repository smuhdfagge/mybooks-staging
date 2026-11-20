{{--
    A business area in the sidebar (audit MB-40, MB-50): a button that opens
    its pages. $open says whether the page shown is one of them.
--}}
@props(['label', 'icon', 'open' => false])

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
            class="w-full group flex min-h-9 items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-brand-800 hover:text-white transition-colors">
        <span class="flex items-center">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}" />
            </svg>
            {{ $label }}
        </span>
        <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    </button>
    <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
        {{ $slot }}
    </div>
</div>
