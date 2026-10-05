{{--
    Where a stock document's goods come from or go to (session 12).

    <x-warehouse-picker :selected="$invoice->warehouse_id ?? null" />
    <x-warehouse-picker label="Return goods to" empty-label="Where they left from" />

    Hidden entirely when the business has only one warehouse (or the module
    is off): the document then uses the default warehouse. Preselects the
    document's warehouse, else the user's last choice, else the default.
--}}
@props([
    'name' => 'warehouse_id',
    'label' => 'Warehouse',
    'selected' => null,
    'help' => null,
    'emptyLabel' => null,
    'wrapperClass' => '',
])

@php
    $tenantId = auth()->user()->tenant_id;
    $choices = \App\Models\Warehouse::choicesFor($tenantId);
    if ($choices->isNotEmpty() && $selected && ! $choices->contains('id', (int) $selected)) {
        // A document's own warehouse stays choosable even if no longer active.
        $own = \App\Models\Warehouse::find($selected);
        if ($own) {
            $choices->push($own);
        }
    }
    $last = session('warehouse.last');
    $fallback = $emptyLabel ? '' : ($last && $choices->contains('id', (int) $last) ? $last : $choices->firstWhere('is_default', true)?->id);
    $current = (string) old($name, $selected ?? $fallback);
@endphp

@if($choices->isNotEmpty())
<div class="{{ $wrapperClass }}">
    <x-field :name="$name" :label="$label" type="select" :help="$help" {{ $attributes }}>
        @if($emptyLabel)
            <option value="">{{ $emptyLabel }}</option>
        @endif
        @foreach($choices as $warehouse)
            <option value="{{ $warehouse->id }}" @selected($current === (string) $warehouse->id)>{{ $warehouse->name }}@if($warehouse->is_default) (default)@endif</option>
        @endforeach
    </x-field>
</div>
@endif
