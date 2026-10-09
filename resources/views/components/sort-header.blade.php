{{--
    A sortable column header for Livewire tables (U11).

    The sort control is a real button, so it can be reached and used from
    the keyboard, and the header says how the column is sorted (aria-sort).

    <x-sort-header field="total" :sort-field="$sortField" :sort-direction="$sortDirection"
                   class="px-6 py-3 text-right ...">Total</x-sort-header>
--}}
@props(['field', 'sortField' => null, 'sortDirection' => 'asc', 'action' => 'sortBy'])

@php
    $active = $sortField === $field;
    $ariaSort = $active ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none';
@endphp

<th scope="col" aria-sort="{{ $ariaSort }}" {{ $attributes }}>
    <button type="button" wire:click="{{ $action }}('{{ $field }}')"
        class="inline-flex items-center gap-1 uppercase tracking-wider font-medium hover:text-gray-900 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded">
        <span>{{ $slot }}</span>
        <svg aria-hidden="true" class="w-4 h-4 {{ $active ? '' : 'invisible' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $active && $sortDirection === 'desc' ? 'M19 9l-7 7-7-7' : 'M5 15l7-7 7 7' }}"/>
        </svg>
    </button>
</th>
