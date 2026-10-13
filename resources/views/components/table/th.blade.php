{{--
    Column header. Sentence case ("Balance due"). Sortable when given a
    field: a real button, and the header says how it is sorted (aria-sort).

    <x-table.th>Customer</x-table.th>
    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
--}}
@props(['field' => null, 'sort' => [null, 'asc'], 'num' => false, 'action' => 'sortBy'])
@php
    [$sortField, $sortDirection] = $sort;
    $active = $field && $sortField === $field;
    $ariaSort = $field ? ($active ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none') : null;
@endphp
@if ($field)
<th scope="col" aria-sort="{{ $ariaSort }}" {{ $attributes->class([$num ? 'num' : '', $active ? '!text-gray-900 dark:!text-white' : '']) }}>
        <button type="button" wire:click="{{ $action }}('{{ $field }}')"
            class="inline-flex items-center gap-1 rounded align-middle leading-5 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:text-white {{ $num ? 'flex-row-reverse' : '' }}">
            <span>{{ $slot }}</span>
            <svg aria-hidden="true" class="h-3.5 w-3.5 {{ $active ? '' : 'opacity-0' }}" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="{{ $active && $sortDirection === 'asc' ? 'M10 4a1 1 0 01.7.3l4 4a1 1 0 01-1.4 1.4L11 7.4V15a1 1 0 11-2 0V7.4L6.7 9.7a1 1 0 01-1.4-1.4l4-4A1 1 0 0110 4z' : 'M10 16a1 1 0 01-.7-.3l-4-4a1 1 0 011.4-1.4L9 12.6V5a1 1 0 112 0v7.6l2.3-2.3a1 1 0 011.4 1.4l-4 4a1 1 0 01-.7.3z' }}"/></svg>
        </button>
</th>
@else
<th scope="col" {{ $attributes->class([$num ? 'num' : '']) }}><span class="inline-flex align-middle leading-5">{{ $slot }}</span></th>
@endif
