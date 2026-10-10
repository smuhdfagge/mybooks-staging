{{-- In the bulk bar, once a whole page is ticked: offer to tick every matching row. --}}
@props(['rows', 'selected' => []])
@php $ids = $rows->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
@if ($ids && ! array_diff($ids, $selected) && $rows->total() > count($selected))
    <button type="button" wire:click="selectAllMatching" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
        Tick all {{ number_format(min($rows->total(), 1000)) }}{{ $rows->total() > 1000 ? ' (the first 1,000)' : '' }}
    </button>
@endif
