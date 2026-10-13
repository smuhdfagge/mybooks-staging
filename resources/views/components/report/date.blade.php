{{-- A date in <x-report.filters>: <x-report.date name="start_date" label="From" :value="$startDate" /> --}}
@props(['name', 'label', 'value' => null, 'type' => 'date'])
<label class="flex flex-col gap-1 text-[13px] font-medium text-gray-700 dark:text-gray-300">
    {{ $label }}
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" {{ $attributes }}
        class="h-9 rounded-md border-gray-300 py-0 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
</label>
