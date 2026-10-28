{{--
    A line's VAT rate, chosen from the business's tax rates (audit MB-20).
    type: sales or purchases. model: the Alpine property holding the
    percentage (e.g. item.tax_rate). name: the Alpine expression for the
    field name. A percentage that isn't one of the rates (an older line, an
    item's own rate, a rate since made inactive) gets its own option, so
    the form posts what the line holds.

    x-model sets the select before the extra option exists, so the browser
    would fall back to the first option ("No VAT") and post 0: x-effect sets
    the select again once Alpine has updated the options.
--}}
@props(['type' => 'sales', 'model', 'name', 'change' => null])
@php
    $choices = \App\Support\TaxChoices::options($type);
    $known = json_encode(array_column($choices, 'rate'));
@endphp
<select aria-label="VAT rate" :name="{{ $name }}" x-model.number="{{ $model }}" x-init="{{ $model }} = Number({{ $model }}) || 0"
    x-effect="const v = Number({{ $model }}) || 0; $nextTick(() => { $el.value = String(v) })"
    @if ($change) @change="{{ $change }}" @endif {{ $attributes->merge(['class' => 'form-control text-sm']) }}>
    @foreach ($choices as $choice)
        <option value="{{ $choice['rate'] }}">{{ $choice['label'] }}</option>
    @endforeach
    <template x-if="! {{ $known }}.includes(Number({{ $model }}) || 0)">
        <option :value="String(Number({{ $model }}) || 0)" x-text="(Number({{ $model }}) || 0) + '%'"></option>
    </template>
</select>
