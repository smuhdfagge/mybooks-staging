{{-- Aged receivables (tables plan T6): what customers owe at a date, by how late it is. Amounts in ₦. --}}
@php $asOfText = \Carbon\Carbon::parse($asOf)->format('j M Y'); @endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Aged receivables" :description="'What customers owe at '.$asOfText.', by how late it is. Amounts in ₦.'"
            export="accounts-receivable" :filters="['as_of' => $asOf]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.control-reconciliation', ['as_of' => $asOf])">Receivables &amp; payables check</x-table.menu-item>
                <x-table.menu-item :href="route('reports.sales-by-customer')">Sales by customer</x-table.menu-item>
                @can('view invoices')<x-table.menu-item :href="route('invoices.index', ['status' => 'overdue'])">Overdue invoices</x-table.menu-item>@endcan
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Aged receivables" :period="'As at '.$asOfText">
        <x-report.filters :action="route('reports.accounts-receivable')">
            <x-report.date name="as_of" label="As at" :value="$asOf" />
        </x-report.filters>

        @include('reports.partials.aged', ['docs' => $invoices, 'party' => 'customer', 'partyName' => 'customer', 'docName' => 'invoice',
            'docRoute' => 'invoices.show', 'numberField' => 'invoice_number', 'dateField' => 'invoice_date'])
    </x-report.sheet>
</x-app-layout>
