{{--
    A labelled form field (U4): label linked to the control with for/id, the
    shared input style, optional help text and the field's error message.

    <x-field name="email" label="Email Address" type="email" :value="old('email')" required />
    <x-field name="notes" label="Notes" type="textarea" rows="2" :value="old('notes')" />
    <x-field name="type" label="Type" type="select"> <option ...> </x-field>

    Other attributes (step, min, placeholder, x-model, ...) go on the control.
    With an error the control gets aria-invalid and aria-describedby (U6).
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'id' => null,
    'required' => false,
    'help' => null,
])

@php
    $id = $id ?? trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $errorKey = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $error = isset($errors) ? $errors->first($errorKey) : null;
    $controlClass = 'form-control'.($error ? ' border-red-500' : '');
    // Screen readers read the help and the error with the field (U6).
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : ''));
    $aria = array_filter(['aria-invalid' => $error ? 'true' : null, 'aria-describedby' => $describedBy ?: null]);
@endphp

@if($label)
    <label for="{{ $id }}" class="form-label">{{ $label }}@if($required) <span class="text-red-500">*</span>@endif</label>
@endif

@if($type === 'textarea')
    <textarea name="{{ $name }}" id="{{ $id }}" @required($required) {{ $attributes->merge(['class' => $controlClass] + $aria) }}>{{ $value }}</textarea>
@elseif($type === 'select')
    <select name="{{ $name }}" id="{{ $id }}" @required($required) {{ $attributes->merge(['class' => $controlClass] + $aria) }}>
        {{ $slot }}
    </select>
@else
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @required($required) {{ $attributes->merge(['class' => $controlClass] + $aria) }}>
@endif

@if($help)
    <p id="{{ $id }}-help" class="form-help">{{ $help }}</p>
@endif
@if($error)
    <p id="{{ $id }}-error" class="form-error">{{ $error }}</p>
@endif
