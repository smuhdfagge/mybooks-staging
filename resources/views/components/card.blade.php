{{--
    White panel used around forms and lists (U4).

    <x-card>...</x-card>   <x-card title="Address" class="p-6">...</x-card>
--}}
@props(['title' => null])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title)
        <h3 class="px-6 pt-5 text-lg font-medium text-gray-900 dark:text-gray-100">{{ $title }}</h3>
    @endif
    {{ $slot }}
</div>
