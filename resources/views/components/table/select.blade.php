{{-- A small filter picker in the toolbar: <x-table.select model="period" label="Date" :options="[...]" /> --}}
@props(['model', 'label', 'options' => []])
<label class="inline-flex items-center">
    <span class="sr-only">{{ $label }}</span>
    <select wire:model.live="{{ $model }}" class="h-9 rounded-md border-gray-300 py-0 pl-2.5 pr-8 text-[13px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $label }}: {{ $text }}</option>
        @endforeach
    </select>
</label>
