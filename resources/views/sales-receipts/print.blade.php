<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Receipt {{ $salesReceipt->receipt_number }} - {{ $tenant->name }}</title>
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
        table.lines td { padding: 8px; border-bottom: 1px solid #eee; }
        .num { text-align: right; }
        table.totals { width: 45%; margin-left: 55%; border-collapse: collapse; }
        table.totals td { padding: 5px 8px; }
        table.totals tr.grand td { border-top: 2px solid #222; font-weight: bold; font-size: 14px; }
        .paid { margin-top: 18px; padding: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .notes { margin-top: 18px; }
        .footer { margin-top: 30px; text-align: center; color: #888; font-size: 10px; }
    </style>
</head>
<body>
@php($symbol = $tenant->currency_symbol ?? '')
<div class="page">
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="company">{{ $tenant->name }}</div>
                    <div class="muted">
                        {{ collect([$tenant->address, $tenant->city, $tenant->state, $tenant->country])->filter()->implode(', ') }}<br>
                        {{ collect([$tenant->phone, $tenant->email])->filter()->implode(' · ') }}
                        @if($tenant->tax_number)<br>Tax number: {{ $tenant->tax_number }}@endif
                    </div>
                </td>
                <td class="title">SALES RECEIPT</td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Received from</div>
                <strong>{{ $salesReceipt->customer?->name ?? 'Walk-in customer' }}</strong><br>
                @if($salesReceipt->customer?->email){{ $salesReceipt->customer->email }}<br>@endif
                @if($salesReceipt->customer?->phone){{ $salesReceipt->customer->phone }}@endif
            </td>
            <td class="num">
                <div class="label">Receipt number</div>
                <strong>{{ $salesReceipt->receipt_number }}</strong><br>
                <div class="label" style="margin-top:6px">Date</div>
                {{ $salesReceipt->receipt_date?->format('M d, Y') }}<br>
                <div class="label" style="margin-top:6px">Paid by</div>
                {{ ucwords(str_replace('_', ' ', (string) $salesReceipt->payment_method)) }}
                @if($salesReceipt->reference)<br>Ref: {{ $salesReceipt->reference }}@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Tax</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($salesReceipt->items as $line)
                <tr>
                    <td>{{ $line->description ?: $line->item?->name }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ $symbol }}{{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="num">{{ (float) $line->tax_rate > 0 ? rtrim(rtrim(number_format((float) $line->tax_rate, 2), '0'), '.').'%' : '—' }}</td>
                    <td class="num">{{ $symbol }}{{ number_format((float) $line->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $symbol }}{{ number_format((float) $salesReceipt->subtotal, 2) }}</td></tr>
        @if((float) $salesReceipt->discount_amount > 0)
            <tr><td>Discount</td><td class="num">−{{ $symbol }}{{ number_format((float) $salesReceipt->discount_amount, 2) }}</td></tr>
        @endif
        <tr><td>Tax</td><td class="num">{{ $symbol }}{{ number_format((float) $salesReceipt->tax_amount, 2) }}</td></tr>
        <tr class="grand"><td>Total paid</td><td class="num">{{ $symbol }}{{ number_format((float) $salesReceipt->total, 2) }}</td></tr>
    </table>

    <div class="paid">Paid in full on {{ $salesReceipt->receipt_date?->format('M d, Y') }}. Thank you for your business.</div>

    @if($salesReceipt->notes)
        <div class="notes"><div class="label">Notes</div>{{ $salesReceipt->notes }}</div>
    @endif

    <div class="footer">{{ $tenant->name }} · Receipt {{ $salesReceipt->receipt_number }}</div>
</div>
</body>
</html>
