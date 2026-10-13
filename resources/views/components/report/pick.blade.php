{{--
    A choice in <x-report.filters>: <x-report.pick name="employee_id" label="Employee" :options="[id => name]" :value="$employeeId" all="Everyone" />
    all: the text for "no choice" (leave it null for no such option).
--}}
@props(['name', 'label', 'options' => [], 'value' => null, 'all' => 'All'])
<label class="flex flex-col gap-1 text-[13px] font-medium text-gray-700 dark:text-gray-300">
    {{ $label }}
    <select name="{{ $name }}" {{ $attributes }}
        class="h-9 max-w-[16rem] rounded-md border-gray-300 py-0 pl-2.5 pr-8 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        @if ($all !== null)<option value="">{{ $all }}</option>@endif
        @foreach ($options as $v => $text)
            <option value="{{ $v }}" @selected((string) $value === (string) $v)>{{ $text }}</option>
        @endforeach
    </select>
</label>
