{{-- A button in the bulk bar. Runs the list's bulk action $action on the ticked rows. --}}
@props(['action', 'confirm' => null, 'danger' => false])
<button type="button" wire:click="runBulk('{{ $action }}')" @if ($confirm) wire:confirm="{{ $confirm }}" @endif wire:loading.attr="disabled"
    class="h-8 rounded-md border bg-white px-3 text-sm font-medium shadow-sm disabled:opacity-50 dark:bg-gray-800 {{ $danger ? 'border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300' : 'border-gray-300 text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700' }}">
    {{ $slot }}
</button>
