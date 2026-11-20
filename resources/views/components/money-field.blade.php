{{--
    A money amount in a form (audit MB-39): the currency in front, the
    amount grouped and to 2 places ("150,000.00"), not a raw number box.
    The controller reads it with Money::fromInput(), which takes the
    separators off.

    <x-money-field name="basic_salary" label="Basic salary" :value="old('basic_salary', $payroll->basic_salary)" required />
--}}
@props(['name', 'label', 'value' => null, 'id' => null, 'required' => false, 'help' => null, 'currency' => null])

@php
    $id = $id ?? trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $error = isset($errors) ? $errors->first($name) : null;
    $amount = \App\Support\Money::fromInput($value);
    $shown = is_numeric($amount) ? number_format((float) $amount, 2) : (string) $value;
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : ''));
@endphp

<label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="text-red-600 dark:text-red-300">*</span>@endif</label>
<div class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-600 dark:text-gray-400" aria-hidden="true">{{ \App\Support\Money::symbol($currency) }}</span>
    <input type="text" inputmode="decimal" autocomplete="off" name="{{ $name }}" id="{{ $id }}" value="{{ $shown }}"
           x-data="moneyInput()" @blur="tidy()" @required($required)
           @if ($error) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           {{ $attributes->class(['form-control pl-8 text-right tabular-nums', 'border-red-500' => $error]) }}>
</div>
@if ($help)
    <p id="{{ $id }}-help" class="form-help">{{ $help }}</p>
@endif
@if ($error)
    <p id="{{ $id }}-error" class="form-error">{{ $error }}</p>
@endif
