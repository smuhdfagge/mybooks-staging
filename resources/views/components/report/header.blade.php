{{--
    Top of a report (tables plan T6): title, one line about it ending
    "Amounts in ₦", Export (print, PDF, CSV) and "More" (All reports first).

    <x-report.header title="Trial balance" description="… Amounts in ₦." export="trial-balance" :filters="['as_of' => $asOf]">
        <x-slot name="more"> <x-table.menu-item href="…">General ledger</x-table.menu-item> </x-slot>
    </x-report.header>

    export: the reports.export.* route name, or leave it out for print only.
    downloads slot: more items for the Export menu (other files).
    exportRoute: a full route name when the export is not reports.export.*.
--}}
@props(['title', 'description' => null, 'export' => null, 'exportRoute' => null, 'filters' => [], 'pdf' => true, 'csv' => true])
@php
    $route = $exportRoute ?? ($export ? 'reports.export.'.$export : null);
    $q = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->all();
@endphp
<x-table.page-header :title="$title" :description="$description">
    <x-slot name="more">
        <x-table.menu-item :href="route('reports.index')">All reports</x-table.menu-item>
        {{ $more ?? '' }}
    </x-slot>
    <x-slot name="actions">
        {{ $actions ?? '' }}
        <x-table.dropdown label="Export" align="right">
            <x-table.menu-item print>Print</x-table.menu-item>
            @if ($route && $pdf)
                <x-table.menu-item :href="route($route, $q + ['format' => 'pdf'])">PDF file</x-table.menu-item>
            @endif
            @if ($route && $csv)
                <x-table.menu-item :href="route($route, $q + ['format' => 'csv'])">CSV file</x-table.menu-item>
            @endif
            {{ $downloads ?? '' }}
        </x-table.dropdown>
    </x-slot>
</x-table.page-header>
