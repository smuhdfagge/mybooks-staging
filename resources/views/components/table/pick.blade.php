{{--
    A filter picker whose options come from the records (customers,
    suppliers, payment methods…): <x-table.pick model="customer" label="Customer" :options="$customers" />
    $options: value => text. The first option is "All".
--}}
@props(['model', 'label', 'options' => [], 'all' => 'All'])
<label class="inline-flex items-center">
    <span class="sr-only">{{ $label }}</span>
    <select wire:model.live="{{ $model }}" class="h-9 max-w-[14rem] rounded-md border-gray-300 py-0 pl-2.5 pr-8 text-[13px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
        <option value="">{{ $label }}: {{ $all }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
</label>
