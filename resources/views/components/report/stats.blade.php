{{--
    The few numbers that sum up a report, in one strip (tables plan T6).
    <x-report.stats :cols="3"> <x-report.stat label="Total debits" :value="…" /> … </x-report.stats>
--}}
@props(['cols' => 4])
@php $grid = [2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-4', 5 => 'sm:grid-cols-3 lg:grid-cols-5'][$cols] ?? 'sm:grid-cols-4'; @endphp
<dl {{ $attributes->merge(['class' => "rpt-stats grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 dark:border-gray-700 dark:bg-gray-700 {$grid}"]) }}>
    {{ $slot }}
</dl>
