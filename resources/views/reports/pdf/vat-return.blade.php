<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>VAT Form 002 - {{ $month }}</title>
    {{-- Laid out like the NRS (formerly FIRS) VAT Form 002 (headquarters). --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; line-height: 1.35; color: #111827; padding: 18px; }
        h1 { font-size: 13px; text-align: center; letter-spacing: 0.5px; }
        h2 { font-size: 10px; text-align: center; font-weight: normal; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        .box td, .box th { border: 1px solid #374151; padding: 3px 5px; vertical-align: top; }
        .box th { background: #e5e7eb; text-align: left; }
        .label { width: 22%; font-weight: bold; background: #f3f4f6; }
        .no { width: 34px; text-align: center; font-weight: bold; }
        .amt { width: 120px; text-align: right; white-space: nowrap; }
        .section td { background: #d1d5db; font-weight: bold; }
        .strong td { font-weight: bold; }
        .note { font-size: 7.5px; color: #4b5563; margin-top: 4px; }
        .warn { border: 1px solid #b45309; background: #fffbeb; padding: 4px 6px; margin: 6px 0; font-size: 8px; }
        .sched td, .sched th { border: 1px solid #9ca3af; padding: 2px 4px; font-size: 7.5px; }
        .sched th { background: #e5e7eb; text-align: left; }
        .r { text-align: right; white-space: nowrap; }
        .page-break { page-break-before: always; }
        .sign td { border: none; padding: 14px 6px 2px; }
        .line { border-bottom: 1px solid #111827; height: 14px; }
    </style>
</head>
<body>
    @php
        $fmt = fn ($v) => $v < 0 ? '('.number_format(abs($v), 2).')' : number_format($v, 2);
        $period = \Carbon\Carbon::parse($from);
    @endphp

    <h1>VALUE ADDED TAX RETURN - FORM 002</h1>
    <h2>Nigeria Revenue Service (formerly Federal Inland Revenue Service)</h2>

    <table class="box">
        <tr><td class="label">Taxpayer name</td><td colspan="3">{{ $tenant->name }}</td></tr>
        <tr><td class="label">TIN</td><td>{{ $tenant->tax_number ?: '-' }}</td><td class="label">Currency</td><td>NGN</td></tr>
        <tr><td class="label">Address</td><td colspan="3">{{ $tenant->address }}</td></tr>
        <tr><td class="label">Telephone</td><td>{{ $tenant->phone }}</td><td class="label">E-mail</td><td>{{ $tenant->email }}</td></tr>
        <tr>
            <td class="label">Period beginning</td><td>{{ $period->format('d/m/Y') }}</td>
            <td class="label">Period ending</td><td>{{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</td>
        </tr>
        @if(!empty($filing))
            <tr><td class="label">Filed</td><td>{{ $filing->filed_on?->format('d/m/Y') }}</td><td class="label">Receipt/reference</td><td>{{ $filing->reference }}</td></tr>
        @endif
    </table>

    @if($unclassified->isNotEmpty())
        <div class="warn">{{ $unclassified->count() }} sales line(s) without VAT are not classified as zero-rated, exempt or out of scope and are included in line 40. Classify them before filing.</div>
    @endif

    <table class="box" style="margin-top: 8px;">
        <tr><th class="no">Line</th><th>Description</th><th class="amt">Amount (NGN)</th></tr>
        @foreach(\App\Services\Accounting\VatReturnForm::SECTIONS as $letter => [$title, $rows])
            <tr class="section"><td colspan="3">Section {{ $letter }}: {{ $title }}</td></tr>
            @foreach($rows as $no => $label)
                <tr class="{{ in_array($no, [40, 45, 75, 95, 120]) ? 'strong' : '' }}">
                    <td class="no">{{ $no }}</td>
                    <td>{{ $label }}</td>
                    <td class="amt">{{ $fmt($lines[$no]) }}</td>
                </tr>
                @if($no === 75 && $importVat > 0)
                    <tr><td></td><td style="padding-left: 14px;">of which VAT on imports</td><td class="amt">{{ $fmt($importVat) }}</td></tr>
                @endif
            @endforeach
        @endforeach
    </table>
    <p class="note">
        Output VAT (line 45) and input VAT (line 75) are the month's movements on the VAT accounts in the ledger
        ({{ $ledger['outputAccount'] }} and {{ $ledger['inputAccount'] }}). Sales are by invoice date; credit notes and refunds count in the month issued.
        Input VAT includes VAT on services and fixed assets (Nigeria Tax Act 2025).
    </p>

    <table class="box" style="margin-top: 8px;">
        <tr><th colspan="3">Reconciliation to the ledger</th></tr>
        <tr><th></th><th class="amt">Output VAT</th><th class="amt">Input VAT</th></tr>
        <tr><td>VAT on the document schedules</td><td class="amt">{{ $fmt($reconciliation['output']['documents']) }}</td><td class="amt">{{ $fmt($reconciliation['input']['documents']) }}</td></tr>
        <tr><td>VAT posted without a document (journals)</td><td class="amt">{{ $fmt($reconciliation['output']['other']) }}</td><td class="amt">{{ $fmt($reconciliation['input']['other']) }}</td></tr>
        <tr><td>Other differences</td><td class="amt">{{ $fmt($reconciliation['output']['unexplained']) }}</td><td class="amt">{{ $fmt($reconciliation['input']['unexplained']) }}</td></tr>
        <tr class="strong"><td>Ledger movement for the month</td><td class="amt">{{ $fmt($reconciliation['output']['ledger']) }}</td><td class="amt">{{ $fmt($reconciliation['input']['ledger']) }}</td></tr>
        <tr class="strong"><td>Return vs ledger difference</td><td class="amt">{{ $fmt($reconciliation['output']['difference']) }}</td><td class="amt">{{ $fmt($reconciliation['input']['difference']) }}</td></tr>
    </table>

    <table class="box" style="margin-top: 8px;">
        <tr><th>Declaration</th></tr>
        <tr><td>I declare that the information given in this return and the attached schedules is correct and complete.</td></tr>
    </table>
    <table class="sign">
        <tr>
            <td style="width: 33%;"><div class="line"></div>Name</td>
            <td style="width: 33%;"><div class="line"></div>Designation</td>
            <td style="width: 33%;"><div class="line"></div>Signature and date</td>
        </tr>
    </table>
    <p class="note">Prepared with MyBooks on {{ now()->format('d/m/Y H:i') }}. Due date for this return: {{ $dueDate->format('d/m/Y') }}.</p>

    @foreach([
        ['Schedule of sales', 'Customer', $salesSchedule],
        ['Schedule of sales adjustments', 'Customer', $adjustmentsSchedule],
        ['Schedule of purchases', 'Supplier', $purchasesSchedule],
    ] as [$title, $partyLabel, $rows])
        <div class="page-break"></div>
        <h1 style="text-align: left;">{{ $title }} - {{ $period->format('F Y') }}</h1>
        <p class="note" style="margin-bottom: 6px;">{{ $tenant->name }} &middot; TIN {{ $tenant->tax_number ?: '-' }}</p>
        <table class="sched">
            <tr>
                <th>{{ $partyLabel }}</th><th>TIN</th><th>Invoice no.</th><th>Date</th><th>Description</th><th>VAT status</th>
                <th class="r">Amount (excl. VAT)</th><th class="r">VAT</th>
            </tr>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->party }}</td><td>{{ $row->tin }}</td><td>{{ $row->number }} ({{ $row->document }})</td><td>{{ $row->date->format('d/m/Y') }}</td>
                    <td>{{ $row->description }}</td><td>{{ \App\Services\Accounting\VatTreatment::label($row->treatment) }}</td>
                    <td class="r">{{ $fmt($row->net) }}</td><td class="r">{{ $fmt($row->vat) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">None.</td></tr>
            @endforelse
            <tr class="strong">
                <td colspan="6"><strong>Total</strong></td>
                <td class="r"><strong>{{ $fmt($rows->sum('net')) }}</strong></td><td class="r"><strong>{{ $fmt($rows->sum('vat')) }}</strong></td>
            </tr>
        </table>
    @endforeach
</body>
</html>
