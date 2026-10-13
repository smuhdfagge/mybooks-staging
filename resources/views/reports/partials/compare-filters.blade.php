{{-- Options for the "compare periods" reports (tables plan T6): which two periods, with dates for "Custom". --}}
@php
    $kinds = ['month' => 'This month and last', 'quarter' => 'This quarter and last', 'year' => 'This year and last', 'ytd' => 'Year to date, this year and last', 'custom' => 'Two periods I choose'];
@endphp
<x-report.filters :action="$action" button="Compare">
    <div class="contents" x-data="{ kind: @js($comparisonType) }">
        <label class="flex flex-col gap-1 text-[13px] font-medium text-gray-700 dark:text-gray-300">
            Compare
            <select name="comparison_type" x-model="kind"
                class="h-9 rounded-md border-gray-300 py-0 pl-2.5 pr-8 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                @foreach ($kinds as $v => $text)
                    <option value="{{ $v }}" @selected($comparisonType === $v)>{{ $text }}</option>
                @endforeach
            </select>
        </label>
        <template x-if="kind === 'custom'">
            <div class="contents">
                <x-report.date name="current_start" label="This period from" :value="request('current_start', now()->startOfMonth()->toDateString())" />
                <x-report.date name="current_end" label="to" :value="request('current_end', now()->toDateString())" />
                <x-report.date name="previous_start" label="Compared with, from" :value="request('previous_start', now()->subMonthNoOverflow()->startOfMonth()->toDateString())" />
                <x-report.date name="previous_end" label="to" :value="request('previous_end', now()->subMonthNoOverflow()->endOfMonth()->toDateString())" />
            </div>
        </template>
    </div>
</x-report.filters>
