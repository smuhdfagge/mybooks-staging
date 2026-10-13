{{-- Header tick box: ticks every row on this page. $ids: the page's row ids (strings). --}}
@props(['ids' => [], 'selected' => [], 'label' => 'Tick every row on this page'])
@php
    $all = $ids && ! array_diff($ids, $selected);
    $some = (bool) array_intersect($ids, $selected);
@endphp
<th scope="col" class="tbl-check">
    <input type="checkbox" class="tbl-checkbox" aria-label="{{ $label }}" @checked($all)
        x-init="$el.indeterminate = {{ $some && ! $all ? 'true' : 'false' }}"
        wire:click="selectPage({{ json_encode(array_values($ids)) }}, $event.target.checked)">
</th>
