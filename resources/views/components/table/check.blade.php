{{-- Row tick box. --}}
@props(['id', 'label'])
<td class="tbl-check">
    <input type="checkbox" class="tbl-checkbox" wire:model.live="selectedItems" value="{{ $id }}" aria-label="Tick {{ $label }}">
</td>
