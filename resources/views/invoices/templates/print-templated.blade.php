@php
    $s = $templateSettings ?? \App\Models\InvoiceTemplate::getDefaultSettings();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }} - {{ $tenant->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: {{ $s['font_family'] }};
            font-size: {{ $s['font_size'] }}px;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        .invoice-container {
            max-width: 800px;
            margin: 20px auto;
            background: #fff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .invoice-header {
            padding: {{ $s['layout'] === 'compact' ? '20px 30px' : '30px 40px' }};
            @if($s['layout'] !== 'minimal')
            border-bottom: {{ $s['border_width'] }}px {{ $s['border_style'] }} {{ $s['primary_color'] }};
            @endif
            background: {{ $s['header_bg_color'] }};
            display: flex;
            justify-content: space-between;
            align-items: {{ $s['layout'] === 'compact' ? 'center' : 'flex-start' }};
        }
        .company-brand { display: flex; align-items: center; gap: 15px; }
        .company-logo {
            max-height: {{ $s['layout'] === 'compact' ? '40px' : '70px' }};
            max-width: 150px;
            object-fit: contain;
        }
        .company-logo-placeholder {
            width: {{ $s['layout'] === 'compact' ? '40px' : '70px' }};
            height: {{ $s['layout'] === 'compact' ? '40px' : '70px' }};
            background: linear-gradient(135deg, {{ $s['primary_color'] }} 0%, {{ $s['accent_color'] }} 100%);
            border-radius: {{ $s['layout'] === 'modern' ? '12px' : '10px' }};
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: {{ $s['layout'] === 'compact' ? '16px' : '28px' }};
            font-weight: bold;
        }
        .company-details h1 {
            font-size: {{ $s['layout'] === 'compact' ? '16px' : ($s['layout'] === 'minimal' ? '24px' : '22px') }};
            color: {{ $s['layout'] === 'minimal' ? $s['primary_color'] : $s['header_text_color'] }};
            font-weight: {{ $s['layout'] === 'minimal' ? '800' : '700' }};
            margin-bottom: 4px;
        }
        .company-details p { font-size: 11px; color: #6B7280; line-height: 1.5; }
        .invoice-title-section { text-align: right; }
        .invoice-title {
            font-size: {{ $s['layout'] === 'compact' ? '20px' : ($s['layout'] === 'minimal' ? '12px' : '32px') }};
            font-weight: 700;
            color: {{ $s['primary_color'] }};
            letter-spacing: {{ $s['layout'] === 'minimal' ? '2px' : '1px' }};
            @if($s['layout'] === 'minimal')
            text-transform: uppercase;
            color: #9CA3AF;
            @endif
            margin-bottom: 5px;
        }
        .invoice-number {
            font-size: {{ $s['layout'] === 'minimal' ? '20px' : '14px' }};
            color: {{ $s['layout'] === 'minimal' ? $s['primary_color'] : '#4B5563' }};
            font-weight: {{ $s['layout'] === 'minimal' ? '700' : '600' }};
        }
        .invoice-status {
            display: inline-block;
            padding: {{ $s['layout'] === 'minimal' ? '3px 10px' : '4px 12px' }};
            border-radius: {{ $s['layout'] === 'minimal' ? '4px' : '20px' }};
            font-size: {{ $s['layout'] === 'minimal' ? '10px' : '11px' }};
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 8px;
        }
        .status-draft { background: #F3F4F6; color: #6B7280; }
        .status-sent { background: #DBEAFE; color: #1D4ED8; }
        .status-paid { background: #D1FAE5; color: #059669; }
        .status-partial { background: #FEF3C7; color: #D97706; }
        .status-overdue { background: #FEE2E2; color: #DC2626; }
        .status-cancelled { background: #F3F4F6; color: #9CA3AF; }
        @if($s['layout'] === 'minimal')
        .status-sent { background: transparent; border: 1px solid {{ $s['primary_color'] }}; color: {{ $s['primary_color'] }}; }
        .status-paid { background: transparent; border: 1px solid #059669; color: #059669; }
        .status-draft { background: transparent; border: 1px solid #6B7280; color: #6B7280; }
        .status-partial { background: transparent; border: 1px solid #D97706; color: #D97706; }
        .status-overdue { background: transparent; border: 1px solid #DC2626; color: #DC2626; }
        @endif
        .invoice-body { padding: {{ $s['layout'] === 'compact' ? '20px 30px' : '30px 40px' }}; }
        @if($s['layout'] === 'minimal')
        .minimal-divider { margin: 0 40px; border-bottom: 1px solid #E5E7EB; }
        @endif
        .invoice-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: {{ $s['layout'] === 'compact' ? '20px' : '30px' }};
            gap: 30px;
        }
        .info-block { flex: 1; }
        .info-block h3 { font-size: 10px; color: #9CA3AF; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-weight: 600; }
        .info-block .name { font-size: 16px; font-weight: 600; color: {{ $s['secondary_color'] }}; margin-bottom: 4px; }
        .info-block p { font-size: 12px; color: #6B7280; margin-bottom: 2px; }
        .dates-block { background: {{ $s['table_header_bg'] }}; padding: 15px 20px; border-radius: 8px; min-width: 200px; }
        .dates-block .date-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .dates-block .date-row:last-child { margin-bottom: 0; }
        .dates-block .label { font-size: 11px; color: #6B7280; }
        .dates-block .value { font-size: 12px; font-weight: 600; color: {{ $s['secondary_color'] }}; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .items-table thead { background: {{ $s['table_header_bg'] }}; }
        .items-table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            color: {{ $s['table_header_text'] }};
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid {{ $s['table_border_color'] }};
        }
        .items-table th.text-right, .items-table td.text-right { text-align: right; }
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid {{ $s['table_border_color'] }};
            vertical-align: top;
        }
        .item-description { font-weight: 500; color: {{ $s['secondary_color'] }}; }
        .item-sku { font-size: 11px; color: #9CA3AF; margin-top: 2px; }
        .totals-section { display: flex; justify-content: flex-end; margin-bottom: 25px; }
        .totals-box { width: 280px; background: {{ $s['table_header_bg'] }}; border-radius: 8px; padding: 20px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; }
        .total-row .label { color: #6B7280; }
        .total-row .value { font-weight: 500; color: {{ $s['secondary_color'] }}; }
        .total-row.discount .value { color: #DC2626; }
        .total-row.grand { border-top: 2px solid {{ $s['table_border_color'] }}; margin-top: 15px; padding-top: 15px; margin-bottom: 0; }
        .total-row.grand .label { font-size: 15px; font-weight: 600; color: {{ $s['secondary_color'] }}; }
        .total-row.grand .value { font-size: 18px; font-weight: 700; color: {{ $s['primary_color'] }}; }
        .total-row.balance-due { margin-top: 10px; padding-top: 10px; border-top: 1px dashed {{ $s['table_border_color'] }}; }
        .total-row.balance-due .label { font-weight: 600; color: {{ $s['secondary_color'] }}; }
        .total-row.balance-due .value { font-weight: 700; color: #DC2626; }
        .notes-terms-section { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 25px; padding-top: 20px; border-top: 1px solid {{ $s['table_border_color'] }}; }
        .notes-box, .terms-box { background: {{ $s['table_header_bg'] }}; padding: 15px; border-radius: 8px; border-left: 3px solid {{ $s['primary_color'] }}; }
        .notes-box h4, .terms-box h4 { font-size: 10px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; font-weight: 600; }
        .notes-box p, .terms-box p { font-size: 12px; color: #4B5563; white-space: pre-line; }
        .invoice-footer { background: {{ $s['footer_bg_color'] }}; padding: 25px 40px; border-top: 1px solid {{ $s['table_border_color'] }}; text-align: center; }
        .footer-company-name { font-weight: 600; color: {{ $s['secondary_color'] }}; margin-bottom: 5px; }
        .footer-contact { font-size: 11px; color: {{ $s['footer_text_color'] }}; }
        .footer-contact a { color: {{ $s['primary_color'] }}; text-decoration: none; }
        .thank-you { font-size: 14px; color: {{ $s['primary_color'] }}; font-weight: 500; margin-bottom: 10px; }
        .print-controls { max-width: 800px; margin: 20px auto; display: flex; justify-content: flex-end; gap: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease; }
        .btn-primary { background: {{ $s['primary_color'] }}; color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: #6B7280; color: white; }
        .btn-secondary:hover { background: #4B5563; }
        @media print {
            body { background: white; }
            .invoice-container { box-shadow: none; margin: 0; max-width: none; }
            .print-controls { display: none; }
            .invoice-header { padding: {{ $s['layout'] === 'compact' ? '15px 25px' : '20px 30px' }}; }
            .invoice-body { padding: {{ $s['layout'] === 'compact' ? '15px 25px' : '20px 30px' }}; }
            .invoice-footer { padding: 20px 30px; }
        }
        @page { size: A4; margin: 10mm; }
    </style>
</head>
<body>
    <div class="print-controls">
        <button onclick="window.print()" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Print Invoice
        </button>
        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Invoice
        </a>
    </div>

    <div class="invoice-container">
        {{-- Header --}}
        <div class="invoice-header">
            <div class="company-brand">
                @if($s['show_logo'])
                    @if($tenant->logo)
                        <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}" class="company-logo">
                    @else
                        <div class="company-logo-placeholder">
                            {{ strtoupper(substr($tenant->name, 0, 2)) }}
                        </div>
                    @endif
                @endif
                <div class="company-details">
                    <h1>{{ $tenant->name }}</h1>
                    @if($tenant->address)<p>{{ $tenant->address }}</p>@endif
                    @if($tenant->city || $tenant->state || $tenant->country)
                        <p>{{ collect([$tenant->city, $tenant->state, $tenant->country])->filter()->implode(', ') }}</p>
                    @endif
                    @if($tenant->email)<p>{{ $tenant->email }}</p>@endif
                    @if($tenant->phone)<p>{{ $tenant->phone }}</p>@endif
                    @if($tenant->tax_number)<p>Tax ID: {{ $tenant->tax_number }}</p>@endif
                </div>
            </div>
            <div class="invoice-title-section">
                <div class="invoice-title">{{ $s['layout'] === 'minimal' ? 'Invoice' : 'INVOICE' }}</div>
                <div class="invoice-number"># {{ $invoice->invoice_number }}</div>
                @if($s['show_status_badge'])
                <div class="invoice-status status-{{ $invoice->status }}">
                    {{ ucfirst($invoice->status) }}
                </div>
                @endif
            </div>
        </div>

        @if($s['layout'] === 'minimal')
        <div class="minimal-divider"></div>
        @endif

        {{-- Body --}}
        <div class="invoice-body">
            <div class="invoice-info-row">
                <div class="info-block">
                    <h3>Bill To</h3>
                    <p class="name">{{ $invoice->customer->name }}</p>
                    @if($invoice->customer->company_name)<p>{{ $invoice->customer->company_name }}</p>@endif
                    @if($invoice->customer->billing_address)<p>{{ $invoice->customer->billing_address }}</p>@endif
                    @if($invoice->customer->city || $invoice->customer->state || $invoice->customer->postal_code)
                        <p>{{ collect([$invoice->customer->city, $invoice->customer->state, $invoice->customer->postal_code])->filter()->implode(', ') }}</p>
                    @endif
                    @if($invoice->customer->email)<p>{{ $invoice->customer->email }}</p>@endif
                    @if($invoice->customer->phone)<p>{{ $invoice->customer->phone }}</p>@endif
                </div>
                <div class="dates-block">
                    <div class="date-row">
                        <span class="label">Invoice Date:</span>
                        <span class="value">{{ $invoice->invoice_date->format('M d, Y') }}</span>
                    </div>
                    <div class="date-row">
                        <span class="label">Due Date:</span>
                        <span class="value">{{ $invoice->due_date->format('M d, Y') }}</span>
                    </div>
                    @if($invoice->reference)
                    <div class="date-row">
                        <span class="label">Reference:</span>
                        <span class="value">{{ $invoice->reference }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: {{ $s['show_tax_column'] ? '45%' : '55%' }};">Description</th>
                        <th class="text-right" style="width: 12%;">Qty</th>
                        <th class="text-right" style="width: 15%;">Unit Price</th>
                        @if($s['show_tax_column'])
                        <th class="text-right" style="width: 12%;">Tax</th>
                        @endif
                        <th class="text-right" style="width: 16%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                    <tr>
                        <td>
                            <div class="item-description">{{ $item->description }}</div>
                            @if($item->item && $item->item->sku)
                                <div class="item-sku">SKU: {{ $item->item->sku }}</div>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                        <td class="text-right">{{ $tenant->currency_symbol }}{{ number_format($item->unit_price, 2) }}</td>
                        @if($s['show_tax_column'])
                        <td class="text-right">{{ number_format($item->tax_rate, 0) }}%</td>
                        @endif
                        <td class="text-right">{{ $tenant->currency_symbol }}{{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals-section">
                <div class="totals-box">
                    <div class="total-row">
                        <span class="label">Subtotal</span>
                        <span class="value">{{ $tenant->currency_symbol }}{{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    @if($invoice->discount_amount > 0)
                    <div class="total-row discount">
                        <span class="label">Discount</span>
                        <span class="value">-{{ $tenant->currency_symbol }}{{ number_format($invoice->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="total-row">
                        <span class="label">Tax</span>
                        <span class="value">{{ $tenant->currency_symbol }}{{ number_format($invoice->tax_amount, 2) }}</span>
                    </div>
                    <div class="total-row grand">
                        <span class="label">Total</span>
                        <span class="value">{{ $tenant->currency_symbol }}{{ number_format($invoice->total, 2) }}</span>
                    </div>
                    @if($s['show_payment_info'] && $invoice->amount_paid > 0)
                    <div class="total-row" style="margin-top: 10px;">
                        <span class="label">Amount Paid</span>
                        <span class="value" style="color: {{ $s['accent_color'] }};">{{ $tenant->currency_symbol }}{{ number_format($invoice->amount_paid, 2) }}</span>
                    </div>
                    @endif
                    @if($s['show_payment_info'] && $invoice->balance_due > 0 && $invoice->balance_due != $invoice->total)
                    <div class="total-row balance-due">
                        <span class="label">Balance Due</span>
                        <span class="value">{{ $tenant->currency_symbol }}{{ number_format($invoice->balance_due, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>

            @if(($s['show_notes'] && $invoice->notes) || ($s['show_terms'] && $invoice->terms))
            <div class="notes-terms-section" @if(!($s['show_notes'] && $invoice->notes) || !($s['show_terms'] && $invoice->terms)) style="grid-template-columns: 1fr" @endif>
                @if($s['show_notes'] && $invoice->notes)
                <div class="notes-box">
                    <h4>Notes</h4>
                    <p>{{ $invoice->notes }}</p>
                </div>
                @endif
                @if($s['show_terms'] && $invoice->terms)
                <div class="terms-box">
                    <h4>Terms & Conditions</h4>
                    <p>{{ $invoice->terms }}</p>
                </div>
                @endif
            </div>
            @endif
        </div>

        @if($s['show_footer'])
        <div class="invoice-footer">
            <div class="thank-you">{{ $s['footer_text'] }}</div>
            <div class="footer-company-name">{{ $tenant->name }}</div>
            <div class="footer-contact">
                @if($tenant->email)
                    <a href="mailto:{{ $tenant->email }}">{{ $tenant->email }}</a>
                @endif
                @if($tenant->email && $tenant->phone) &nbsp;|&nbsp; @endif
                @if($tenant->phone){{ $tenant->phone }}@endif
                @if($tenant->website)
                    &nbsp;|&nbsp;
                    <a href="{{ $tenant->website }}" target="_blank">{{ str_replace(['https://', 'http://'], '', $tenant->website) }}</a>
                @endif
            </div>
        </div>
        @endif
    </div>
</body>
</html>
