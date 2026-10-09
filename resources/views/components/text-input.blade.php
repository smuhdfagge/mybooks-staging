@props(['disabled' => false])

@php
    // Mark the field invalid and point at its <x-input-error id="name-error"> (U6).
    $field = $attributes->get('name');
    $aria = $field && isset($errors) && $errors->has($field)
        ? ['aria-invalid' => 'true', 'aria-describedby' => preg_replace('/[^A-Za-z0-9_-]+/', '-', $field).'-error']
        : [];
@endphp

<input @disabled($disabled) {{ $attributes->merge($aria + ['class' => 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm']) }}>
