{{--
    Dialog box (U7). Keeps keyboard focus inside while open, closes with
    Escape or a click on the backdrop, and puts focus back on the button
    that opened it.

    Open / close it with:
      data-open-modal="name" / data-close-modal="name"     (plain pages, see dom-actions)
      $dispatch('open-modal', 'name') / $dispatch('close-modal', 'name')   (Alpine)

    <x-modal name="dispose-asset" title="Dispose Asset" maxWidth="md"> ... </x-modal>
--}}
@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'title' => null,
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
$titleId = 'modal-'.\Illuminate\Support\Str::slug($name).'-title';
@endphp

<div
    x-data="{ show: @js($show) }"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show && (show = false)"
    x-show="show"
    data-modal="{{ $name }}"
    role="dialog"
    aria-modal="true"
    @if($title) aria-labelledby="{{ $titleId }}" @endif
    {{ $attributes->except(['focusable'])->merge(['class' => 'fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50']) }}
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div
        x-show="show"
        class="fixed inset-0 transform transition-all"
        x-on:click="show = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
    </div>

    <div
        x-show="show"
        x-trap.inert.noscroll="show"
        class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        @if($title)
            <h3 id="{{ $titleId }}" class="px-6 pt-5 text-lg font-medium text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        @endif
        {{ $slot }}
    </div>
</div>
