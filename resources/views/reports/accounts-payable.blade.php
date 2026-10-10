{{-- Aged payables (tables plan T6): what you owe suppliers at a date, by how late it is. Amounts in ₦. --}}
@php $asOfText = \Carbon\Carbon::parse($asOf)->format('j M Y'); @endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Aged payables" :description="'What you owe suppliers at '.$asOfText.', by how late it is. Amounts in ₦.'"
            export="accounts-payable" :filters="['as_of' => $asOf]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.control-reconciliation', ['as_of' => $asOf])">Receivables &amp; payables check</x-table.menu-item>
                <x-table.menu-item :href="route('reports.purchase-by-vendor')">Purchases by supplier</x-table.menu-item>
                @can('view bills')<x-table.menu-item :href="route('bills.index', ['status' => 'overdue'])">Overdue bills</x-table.menu-item>@endcan
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Aged payables" :period="'As at '.$asOfText">
        <x-report.filters :action="route('reports.accounts-payable')">
            <x-report.date name="as_of" label="As at" :value="$asOf" />
        </x-report.filters>

        @include('reports.partials.aged', ['docs' => $bills, 'party' => 'vendor', 'partyName' => 'supplier', 'docName' => 'bill',
            'docRoute' => 'bills.show', 'numberField' => 'bill_number', 'dateField' => 'bill_date'])
    </x-report.sheet>
</x-app-layout>
