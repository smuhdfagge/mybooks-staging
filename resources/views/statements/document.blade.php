{{--
    Customer or supplier statement for printing and for the PDF (session 10).
    Plain HTML and inline styles so the browser and DomPDF show the same
    A4 page. $statement (App\Services\Statements\Statement), $tenant, $logo
    (data URI or null), $forPdf.
--}}
@php
    $s = $statement;
    $party = $s->party;
    $cur = $tenant->currency ?? null;
    $m = fn ($v) => \App\Support\Money::format($v, $cur);
    $address = $s->isCustomer()
        ? collect([$party->billing_address, collect([$party->city, $party->state])->filter()->implode(', '), $party->country])->filter()
        : collect([$party->address, collect([$party->city, $party->state])->filter()->implode(', '), $party->country])->filter();
    $ageing = $s->ageing;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $s->title() }} - {{ $party->name }} - {{ $tenant->name }}</title>
    <style>
        @page { margin: 28px 34px 44px 34px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; line-height: 1.45; color: #1f2937; }
        .page { padding: 0; }
        .screen .page { max-width: 800px; margin: 0 auto; padding: 32px 40px; }
        table { border-collapse: collapse; }
        .header { width: 100%; border-bottom: 2px solid #4f46e5; margin-bottom: 16px; }
        .header td { vertical-align: top; padding-bottom: 12px; }
        .logo { max-height: 56px; max-width: 170px; margin-bottom: 6px; }
        .company { font-size: 16px; font-weight: bold; color: #111827; }
        .muted { color: #6b7280; }
        .title { font-size: 18px; font-weight: bold; color: #4f46e5; text-align: right; }
        .meta { width: 100%; margin-bottom: 14px; }
        .meta td { vertical-align: top; width: 50%; }
        .label { font-size: 8.5px; text-transform: uppercase; color: #6b7280; letter-spacing: .5px; }
        .summary { width: 100%; margin-bottom: 14px; }
        .summary td { border: 1px solid #e5e7eb; background: #f9fafb; padding: 6px 8px; width: 25%; }
        .summary .value { font-size: 12.5px; font-weight: bold; }
        .summary .due { background: #eef2ff; border-color: #c7d2fe; }
        table.lines { width: 100%; margin-bottom: 12px; }
        table.lines th { background: #f3f4f6; text-align: left; font-size: 8.5px; text-transform: uppercase; padding: 6px; border-bottom: 1px solid #d1d5db; color: #374151; }
        table.lines td { padding: 5px 6px; border-bottom: 1px solid #eef0f3; vertical-align: top; }
        table.lines tr.bf td { background: #fafafa; font-style: italic; }
        table.lines tr.total td { border-top: 1.5px solid #374151; border-bottom: 0; font-weight: bold; }
        table.lines thead { display: table-header-group; }
        table.lines tr { page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }
        .overdue { color: #b91c1c; }
        h3 { font-size: 11px; margin: 10px 0 6px; color: #111827; }
        table.ageing { width: 100%; margin-top: 8px; page-break-inside: avoid; }
        table.ageing th { font-size: 8.5px; text-transform: uppercase; color: #374151; background: #f3f4f6; padding: 5px 6px; border: 1px solid #e5e7eb; text-align: right; }
        table.ageing td { padding: 6px; border: 1px solid #e5e7eb; text-align: right; white-space: nowrap; }
        table.ageing td.total, table.ageing th.total { background: #eef2ff; font-weight: bold; }
        .note { margin-top: 14px; padding: 8px 10px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 9.5px; color: #4b5563; }
        .print-bar { padding: 10px 40px; background: #eef2ff; text-align: right; }
        .print-bar button { padding: 6px 14px; background: #4f46e5; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
        @media print { .print-bar { display: none; } .screen .page { padding: 0; } }
    </style>
</head>
<body class="{{ empty($forPdf) ? 'screen' : '' }}">
@if(empty($forPdf))
    <div class="print-bar"><button type="button" data-print>Print</button></div>
@endif
<div class="page">
    <table class="header">
        <tr>
            <td>
                @if($logo)<img src="{{ $logo }}" alt="" class="logo"><br>@endif
                <div class="company">{{ $tenant->name }}</div>
                <div class="muted">
                    @if($tenant->address){{ collect([$tenant->address, $tenant->city, $tenant->state, $tenant->country])->filter()->implode(', ') }}<br>@endif
                    {{ collect([$tenant->phone, $tenant->email])->filter()->implode(' · ') }}
                    @if($tenant->tax_number)<br>TIN: {{ $tenant->tax_number }}@endif
                </div>
            </td>
            <td class="title">
                {{ strtoupper($s->title()) }}<br>
                <span class="muted" style="font-size:10px; font-weight:normal">{{ $s->isActivity() ? 'Activity' : 'Open items' }} · {{ $s->periodText() }}</span>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="label">{{ $s->isCustomer() ? 'Customer' : 'Supplier' }}</div>
                <strong>{{ $party->name }}</strong><br>
                @if($party->company_name && $party->company_name !== $party->name){{ $party->company_name }}<br>@endif
                @foreach($address as $line){{ $line }}<br>@endforeach
                @if($party->email){{ $party->email }}<br>@endif
                @if($party->phone){{ $party->phone }}@endif
            </td>
            <td class="num">
                <div class="label">Statement date</div>
                {{ \Carbon\Carbon::parse($s->to)->format('j M Y') }}<br>
                @if($s->isActivity())
                    <div class="label" style="margin-top:5px">Period</div>
                    {{ $s->periodText() }}<br>
                @endif
                <div class="label" style="margin-top:5px">{{ $s->balanceText() }}</div>
                <strong style="font-size:13px">{{ $m(abs($s->closing)) }}</strong>
            </td>
        </tr>
    </table>

    @if($s->isActivity())
        <table class="summary">
            <tr>
                <td><div class="label">Opening balance</div><div class="value">{{ $m($s->opening) }}</div></td>
                <td><div class="label">{{ $s->chargesHeading() }}</div><div class="value">{{ $m($s->totalCharges) }}</div></td>
                <td><div class="label">{{ $s->creditsHeading() }}</div><div class="value">{{ $m($s->totalCredits) }}</div></td>
                <td class="due"><div class="label">Closing balance</div><div class="value">{{ $m($s->closing) }}</div></td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width:62px">Date</th>
                    <th>Details</th>
                    <th class="num" style="width:78px">{{ $s->chargesHeading() }}</th>
                    <th class="num" style="width:78px">{{ $s->creditsHeading() }}</th>
                    <th class="num" style="width:84px">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bf">
                    <td>{{ \Carbon\Carbon::parse($s->from)->format('d/m/Y') }}</td>
                    <td>Balance brought forward</td>
                    <td></td><td></td>
                    <td class="num">{{ $m($s->opening) }}</td>
                </tr>
                @foreach($s->rows as $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                        <td>{{ $row['label'] }}@if($row['due_date'] && $row['charge'] > 0)<span class="muted"> · due {{ \Carbon\Carbon::parse($row['due_date'])->format('d/m/Y') }}</span>@endif</td>
                        <td class="num">{{ $row['charge'] > 0 ? $m($row['charge']) : '' }}</td>
                        <td class="num">{{ $row['credit'] > 0 ? $m($row['credit']) : '' }}</td>
                        <td class="num">{{ $m($row['balance']) }}</td>
                    </tr>
                @endforeach
                @if(! count($s->rows))
                    <tr><td></td><td class="muted">Nothing in this period.</td><td></td><td></td><td></td></tr>
                @endif
                <tr class="total">
                    <td></td>
                    <td>Closing balance</td>
                    <td class="num">{{ $m($s->totalCharges) }}</td>
                    <td class="num">{{ $m($s->totalCredits) }}</td>
                    <td class="num">{{ $m($s->closing) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <h3>{{ $s->isCustomer() ? 'Unpaid invoices' : 'Unpaid bills' }}</h3>
        <table class="lines">
            <thead>
                <tr>
                    <th style="width:62px">Date</th>
                    <th>Details</th>
                    <th style="width:62px">Due</th>
                    <th class="num" style="width:58px">Days overdue</th>
                    <th class="num" style="width:78px">Original</th>
                    <th class="num" style="width:84px">Still due</th>
                </tr>
            </thead>
            <tbody>
                @forelse($s->open['items'] as $item)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($item['date'])->format('d/m/Y') }}</td>
                        <td>{{ $item['label'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($item['due_date'])->format('d/m/Y') }}</td>
                        <td class="num {{ $item['days_overdue'] > 0 ? 'overdue' : '' }}">{{ $item['days_overdue'] > 0 ? $item['days_overdue'] : '' }}</td>
                        <td class="num">{{ $m($item['total']) }}</td>
                        <td class="num">{{ $m($item['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td></td><td class="muted" colspan="5">Nothing unpaid.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if(count($s->open['credits']))
            <h3>Payments and credits not yet used</h3>
            <table class="lines">
                <thead><tr><th style="width:62px">Date</th><th>Details</th><th style="width:110px">Reference</th><th class="num" style="width:84px">Amount</th></tr></thead>
                <tbody>
                    @foreach($s->open['credits'] as $credit)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($credit['date'])->format('d/m/Y') }}</td>
                            <td>{{ $credit['label'] }}</td>
                            <td>{{ $credit['reference'] }}</td>
                            <td class="num">-{{ $m($credit['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <table class="lines">
            <tr class="total"><td>{{ $s->balanceText() }}</td><td class="num" style="width:84px">{{ $m($s->closing) }}</td></tr>
        </table>
    @endif

    <table class="ageing">
        <tr>
            @foreach(\App\Services\Statements\Subledger::AGEING_LABELS as $key => $label)
                <th class="{{ $key === 'total' ? 'total' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach(\App\Services\Statements\Subledger::AGEING_LABELS as $key => $label)
                <td class="{{ $key === 'total' ? 'total' : '' }}">{{ $m($ageing[$key]) }}</td>
            @endforeach
        </tr>
    </table>

    <div class="note">
        @if($s->isCustomer())
            Please check this statement. If anything doesn't match your records, contact us{{ $tenant->email ? ' at '.$tenant->email : '' }}{{ $tenant->phone ? ' or '.$tenant->phone : '' }}.
            Ageing is worked out from each invoice's due date as at {{ \Carbon\Carbon::parse($s->to)->format('j M Y') }}.
        @else
            This is our record of your account. Please check it against your own records and let us know of any difference{{ $tenant->email ? ' at '.$tenant->email : '' }}.
            Ageing is worked out from each bill's due date as at {{ \Carbon\Carbon::parse($s->to)->format('j M Y') }}.
        @endif
    </div>
</div>
@if(empty($forPdf))
    @include('partials.dom-actions')
@endif
</body>
</html>
