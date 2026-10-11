{{-- From/To for the payroll reports (tables plan T6), plus any extra pickers passed in $slot. --}}
<x-report.filters :action="$action">
    <x-report.date name="start_date" label="Pay date from" :value="$startDate" />
    <x-report.date name="end_date" label="to" :value="$endDate" />
    {{ $extra ?? '' }}
</x-report.filters>
