{{--
    One line in a menu. A link (href), a form (post="url", with method="DELETE"
    or PUT to spoof it), or a Livewire call (wire="method(1)"). danger: red,
    after a line.
    <x-table.menu-item href="…">View</x-table.menu-item>
    <x-table.menu-item post="…" confirm="Release this invoice?">Release</x-table.menu-item>
    <x-table.menu-item wire="deleteOne(5)" confirm="Delete INV-5?" danger>Delete</x-table.menu-item>
--}}
@props(['href' => null, 'post' => null, 'method' => null, 'wire' => null, 'confirm' => null, 'danger' => false, 'newTab' => false])
@php
    $cls = 'flex w-full items-center rounded-md px-2.5 py-1.5 text-left text-sm focus:outline-none '.($danger
        ? 'text-red-700 hover:bg-red-50 focus:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/30 dark:focus:bg-red-900/30'
        : 'text-gray-800 hover:bg-gray-100 focus:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700');
@endphp
@if ($danger)<div class="my-1 border-t border-gray-100 dark:border-gray-700" role="separator"></div>@endif
@if ($post)
    <form method="POST" action="{{ $post }}" @if ($confirm) data-confirm="{{ $confirm }}" @endif>
        @csrf
        @if ($method)@method($method)@endif
        <button type="submit" role="menuitem" class="{{ $cls }}">{{ $slot }}</button>
    </form>
@elseif ($wire)
    <button type="button" role="menuitem" wire:click="{{ $wire }}" @if ($confirm) wire:confirm="{{ $confirm }}" @endif class="{{ $cls }}">{{ $slot }}</button>
@else
    <a href="{{ $href }}" role="menuitem" @if ($newTab) target="_blank" rel="noopener" @endif class="{{ $cls }}">{{ $slot }}</a>
@endif
