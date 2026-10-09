{{--
    Printable document (quotation, delivery note, credit note). Plain HTML
    and inline styles so the same page prints from the browser and renders
    as a PDF with DomPDF.

    @extends('documents.layout', ['title' => 'QUOTATION', 'number' => ..., 'tenant' => $tenant])
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} {{ $number }} - {{ $tenant->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #222; }
        .page { padding: 32px 40px; }
        .header { border-bottom: 2px solid #1F4E79; padding-bottom: 14px; margin-bottom: 20px; }
        .header table { width: 100%; }
        .company { font-size: 18px; font-weight: bold; }
        .muted { color: #666; }
        .title { font-size: 20px; font-weight: bold; color: #1F4E79; text-align: right; }
        .meta { width: 100%; margin-bottom: 20px; }
        .meta td { vertical-align: top; width: 50%; }
        .label { font-size: 10px; text-transform: uppercase; color: #666; letter-spacing: .5px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.lines th { background: #f3f4f6; text-align: left; font-size: 10px; text-transform: uppercase; padding: 8px; border-bottom: 1px solid #ddd; }
        table.lines td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .num { text-align: right; }
        table.totals { width: 45%; margin-left: 55%; border-collapse: collapse; }
        table.totals td { padding: 5px 8px; }
        table.totals tr.grand td { border-top: 2px solid #222; font-weight: bold; font-size: 14px; }
        .box { margin-top: 18px; padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; }
        .notes { margin-top: 18px; }
        .signatures { width: 100%; margin-top: 50px; }
        .signatures td { width: 50%; padding-right: 30px; }
        .sign-line { border-top: 1px solid #222; margin-top: 40px; padding-top: 4px; font-size: 10px; color: #666; }
        .footer { margin-top: 30px; text-align: center; color: #888; font-size: 10px; }
        .print-bar { padding: 10px 40px; background: #EEF3F8; text-align: right; }
        .print-bar button { padding: 6px 14px; background: #1F4E79; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
        @media print { .print-bar { display: none; } }
    </style>
</head>
<body>

@if(empty($forPdf))
    <div class="print-bar"><button type="button" data-print>Print</button></div>
@endif
<div class="page">
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="company">{{ $tenant->name }}</div>
                    <div class="muted">
                        {{ collect([$tenant->address, $tenant->city, $tenant->state, $tenant->country])->filter()->implode(', ') }}<br>
                        {{ collect([$tenant->phone, $tenant->email])->filter()->implode(' · ') }}
                        @if($tenant->tax_number)<br>TIN: {{ $tenant->tax_number }}@endif
                    </div>
                </td>
                <td class="title">{{ $title }}</td>
            </tr>
        </table>
    </div>

    @yield('content')

    <div class="footer">{{ $tenant->name }} · {{ ucwords(strtolower($title)) }} {{ $number }}</div>
</div>
@if(empty($forPdf))
    @include('partials.dom-actions')
@endif
</body>
</html>
