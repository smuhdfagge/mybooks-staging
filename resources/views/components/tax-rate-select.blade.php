{{--
    A line's VAT rate, chosen from the business's tax rates (audit MB-20).
    type: sales or purchases. model: the Alpine property holding the
    percentage (e.g. item.tax_rate). name: the Alpine expression for the
    field name. A percentage that isn't one of the rates (an older line)
    stays selectable so editing doesn't change it.
--}}
@props(['type' => 'sales', 'model', 'name', 'change' => null])
@php
    $choices = \App\Support\TaxChoices::options($type);
    $known = json_encode(array_column($choices, 'rate'));
@endphp
<select aria-label="VAT rate" :name="{{ $name }}" x-model.number="{{ $model }}" x-init="{{ $model }} = Number({{ $model }}) || 0"
    @if ($change) @change="{{ $change }}" @endif {{ $attributes->merge(['class' => 'form-control text-sm']) }}>
    @foreach ($choices as $choice)
        <option value="{{ $choice['rate'] }}">{{ $choice['label'] }}</option>
    @endforeach
    <option :value="{{ $model }}" x-show="! {{ $known }}.includes(Number({{ $model }}))" x-text="Number({{ $model }}) + '%'"></option>
</select>
