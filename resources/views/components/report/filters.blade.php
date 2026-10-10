{{--
    The report's options as one row (tables plan T6): dates and pickers,
    then "Run report". An ordinary GET form; hidden when printing.

    <x-report.filters :action="route('reports.trial-balance')">
        <x-report.date name="as_of" label="As at" :value="$asOf" />
    </x-report.filters>
--}}
@props(['action', 'button' => 'Run report'])
<form method="GET" action="{{ $action }}" class="no-print flex flex-wrap items-end gap-x-3 gap-y-2" aria-label="Report options">
    {{ $slot }}
    <button type="submit" class="tbl-chip h-9">{{ $button }}</button>
</form>
