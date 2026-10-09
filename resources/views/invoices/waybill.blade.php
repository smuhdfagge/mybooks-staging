<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waybill {{ $invoice->waybill_number }} - {{ $tenant->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        
        .waybill-container {
            max-width: 800px;
            margin: 20px auto;
            background: #fff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .waybill-header {
            padding: 30px 40px;
            border-bottom: 3px solid #3A6798;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .company-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .company-logo {
            max-height: 70px;
            max-width: 150px;
            object-fit: contain;
        }
        
        .company-logo-placeholder {
            width: 70px;
            height: 70px;
            background: #1F4E79;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
        }
        
        .company-details h1 {
            font-size: 22px;
            color: #1F2937;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .company-details p {
            font-size: 11px;
            color: #6B7280;
            line-height: 1.5;
        }
        
        .waybill-title-section {
            text-align: right;
        }
        
        .waybill-title {
            font-size: 32px;
            font-weight: 700;
            color: #3A6798;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }
        
        .waybill-number {
            font-size: 14px;
            color: #4B5563;
            font-weight: 600;
        }
        
        .waybill-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 8px;
            background: #D1FAE5;
            color: #2E7D32;
        }
        
        .waybill-body {
            padding: 30px 40px;
        }
        
        .shipping-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            gap: 30px;
        }
        
        .info-block {
            flex: 1;
            background: #F9FAFB;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3A6798;
        }
        
        .info-block h3 {
            font-size: 10px;
            color: #3A6798;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .info-block .name {
            font-size: 16px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 4px;
        }
        
        .info-block p {
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 2px;
        }
        
        .details-strip {
            display: flex;
            justify-content: space-between;
            background: #EEF3F8;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .detail-item {
            text-align: center;
            flex: 1;
        }
        
        .detail-item:not(:last-child) {
            border-right: 1px solid #B4C8DD;
        }
        
        .detail-item .label {
            display: block;
            font-size: 10px;
            color: #3A6798;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .detail-item .value {
            font-size: 13px;
            font-weight: 600;
            color: #1F2937;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        
        .items-table thead {
            background: #3A6798;
        }
        
        .items-table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table th:last-child {
            text-align: center;
        }
        
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: top;
        }
        
        .items-table td:last-child {
            text-align: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .items-table tbody tr:nth-child(even) {
            background: #FAFAFA;
        }
        
        .items-table tbody tr:hover {
            background: #F3F4F6;
        }
        
        .item-name {
            font-weight: 600;
            color: #1F2937;
        }
        
        .item-desc {
            font-size: 11px;
            color: #9CA3AF;
            margin-top: 2px;
        }
        
        .item-sku {
            font-size: 11px;
            color: #3A6798;
        }
        
        .summary-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        
        .summary-box {
            width: 250px;
            background: #F9FAFB;
            border-radius: 8px;
            padding: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 13px;
        }
        
        .summary-row .label {
            color: #6B7280;
        }
        
        .summary-row .value {
            font-weight: 500;
            color: #1F2937;
        }
        
        .summary-row.total {
            border-top: 2px solid #3A6798;
            margin-top: 15px;
            padding-top: 15px;
            margin-bottom: 0;
        }
        
        .summary-row.total .label {
            font-size: 14px;
            font-weight: 600;
            color: #1F2937;
        }
        
        .summary-row.total .value {
            font-size: 16px;
            font-weight: 700;
            color: #3A6798;
        }
        
        .notes-section {
            background: #FFFBEB;
            border: 1px solid #FCD34D;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 30px;
        }
        
        .notes-section h4 {
            font-size: 10px;
            color: #92400E;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .notes-section p {
            font-size: 12px;
            color: #78350F;
            white-space: pre-line;
        }
        
        .signatures-section {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .signature-box {
            flex: 1;
            text-align: center;
        }
        
        .signature-line {
            border-top: 2px solid #E5E7EB;
            margin-top: 60px;
            padding-top: 10px;
            font-size: 11px;
            color: #6B7280;
            font-weight: 500;
        }
        
        .waybill-footer {
            background: #F9FAFB;
            padding: 25px 40px;
            border-top: 1px solid #E5E7EB;
            text-align: center;
        }
        
        .footer-note {
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 8px;
        }
        
        .footer-company-name {
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 5px;
        }
        
        .footer-contact {
            font-size: 11px;
            color: #6B7280;
        }
        
        .footer-contact a {
            color: #3A6798;
            text-decoration: none;
        }
        
        /* Print Controls */
        .print-controls {
            max-width: 800px;
            margin: 20px auto;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #3A6798;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1F4E79;
        }
        
        .btn-secondary {
            background: #6B7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4B5563;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .waybill-container {
                box-shadow: none;
                margin: 0;
                max-width: none;
            }
            
            .print-controls {
                display: none;
            }
            
            .waybill-header {
                padding: 20px 30px;
            }
            
            .waybill-body {
                padding: 20px 30px;
            }
            
            .waybill-footer {
                padding: 20px 30px;
            }
            
            .items-table thead {
                background: #3A6798 !important;
            }
            
            .items-table th {
                color: white !important;
            }
        }
        
        @page {
            size: A4;
            margin: 10mm;
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="print-controls">
        <button data-print class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Print Waybill
        </button>
        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Invoice
        </a>
    </div>

    <!-- Waybill -->
    <div class="waybill-container">
        <!-- Header -->
        <div class="waybill-header">
            <div class="company-brand">
                @if($tenant->logo)
                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}" class="company-logo">
                @else
                    <div class="company-logo-placeholder">
                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                    </div>
                @endif
                <div class="company-details">
                    <h1>{{ $tenant->name }}</h1>
                    @if($tenant->address)
                        <p>{{ $tenant->address }}</p>
                    @endif
                    @if($tenant->city || $tenant->state || $tenant->country)
                        <p>{{ collect([$tenant->city, $tenant->state, $tenant->country])->filter()->implode(', ') }}</p>
                    @endif
                    @if($tenant->email)
                        <p>{{ $tenant->email }}</p>
                    @endif
                    @if($tenant->phone)
                        <p>{{ $tenant->phone }}</p>
                    @endif
                </div>
            </div>
            <div class="waybill-title-section">
                <div class="waybill-title">WAYBILL</div>
                <div class="waybill-number"># {{ $invoice->waybill_number }}</div>
                <div class="waybill-status">Released</div>
            </div>
        </div>

        <!-- Body -->
        <div class="waybill-body">
            <!-- Ship To / Ship From -->
            <div class="shipping-info-row">
                <div class="info-block">
                    <h3>Ship To (Customer)</h3>
                    <p class="name">{{ $invoice->customer->name }}</p>
                    @if($invoice->customer->company_name)
                        <p>{{ $invoice->customer->company_name }}</p>
                    @endif
                    @if($invoice->customer->address)
                        <p>{{ $invoice->customer->address }}</p>
                    @endif
                    @if($invoice->customer->city || $invoice->customer->state || $invoice->customer->postal_code)
                        <p>{{ collect([$invoice->customer->city, $invoice->customer->state, $invoice->customer->postal_code])->filter()->implode(', ') }}</p>
                    @endif
                    @if($invoice->customer->phone)
                        <p>Phone: {{ $invoice->customer->phone }}</p>
                    @endif
                    @if($invoice->customer->email)
                        <p>Email: {{ $invoice->customer->email }}</p>
                    @endif
                </div>
                <div class="info-block">
                    <h3>Ship From</h3>
                    <p class="name">{{ $tenant->name }}</p>
                    @if($tenant->address)
                        <p>{{ $tenant->address }}</p>
                    @endif
                    @if($tenant->city || $tenant->state || $tenant->postal_code)
                        <p>{{ collect([$tenant->city, $tenant->state, $tenant->postal_code])->filter()->implode(', ') }}</p>
                    @endif
                    @if($tenant->phone)
                        <p>Phone: {{ $tenant->phone }}</p>
                    @endif
                    @if($tenant->email)
                        <p>Email: {{ $tenant->email }}</p>
                    @endif
                </div>
            </div>

            <!-- Reference Details -->
            <div class="details-strip">
                <div class="detail-item">
                    <span class="label">Invoice Number</span>
                    <span class="value">{{ $invoice->invoice_number }}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Invoice Date</span>
                    <span class="value">{{ $invoice->invoice_date->format('M d, Y') }}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Release Date</span>
                    <span class="value">{{ $invoice->released_at->format('M d, Y') }}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Total Items</span>
                    <span class="value">{{ $invoice->items->count() }}</span>
                </div>
                <div class="detail-item">
                    <span class="label">Total Qty</span>
                    <span class="value">{{ number_format($invoice->items->sum('quantity'), 2) }}</span>
                </div>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 15%;">Item Code</th>
                        <th style="width: 45%;">Description</th>
                        <th style="width: 15%;">Unit</th>
                        <th style="width: 20%;">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            @if($item->item && $item->item->sku)
                                <span class="item-sku">{{ $item->item->sku }}</span>
                            @else
                                <span style="color: #9CA3AF;">N/A</span>
                            @endif
                        </td>
                        <td>
                            <div class="item-name">{{ $item->item->name ?? $item->description }}</div>
                            @if($item->description && $item->item && $item->description !== $item->item->name)
                                <div class="item-desc">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td>{{ $item->item->unit ?? 'pcs' }}</td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Notes Section -->
            @if($invoice->notes)
            <div class="notes-section">
                <h4>Special Instructions / Notes</h4>
                <p>{{ $invoice->notes }}</p>
            </div>
            @endif

            <!-- Summary -->
            <div class="summary-section">
                <div class="summary-box">
                    <div class="summary-row">
                        <span class="label">Total Items</span>
                        <span class="value">{{ $invoice->items->count() }}</span>
                    </div>
                    <div class="summary-row total">
                        <span class="label">Total Quantity</span>
                        <span class="value">{{ number_format($invoice->items->sum('quantity'), 2) }} units</span>
                    </div>
                </div>
            </div>

            <!-- Signatures -->
            <div class="signatures-section">
                <div class="signature-box">
                    <div class="signature-line">Prepared By / Warehouse</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Driver / Transporter</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Received By / Customer</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="waybill-footer">
            <div class="footer-note">
                This waybill confirms the release of goods from inventory for Invoice #{{ $invoice->invoice_number }}
            </div>
            <div class="footer-company-name">{{ $tenant->name }}</div>
            <div class="footer-contact">
                @if($tenant->email)
                    <a href="mailto:{{ $tenant->email }}">{{ $tenant->email }}</a>
                @endif
                @if($tenant->email && $tenant->phone)
                    &nbsp;|&nbsp;
                @endif
                @if($tenant->phone)
                    {{ $tenant->phone }}
                @endif
                @if($tenant->website)
                    &nbsp;|&nbsp;
                    <a href="{{ $tenant->website }}" target="_blank">{{ str_replace(['https://', 'http://'], '', $tenant->website) }}</a>
                @endif
            </div>
            <div style="margin-top: 10px; font-size: 10px; color: #9CA3AF;">
                Generated on {{ now()->format('F d, Y \a\t H:i:s') }}
            </div>
        </div>
    </div>
    @include('partials.dom-actions')
</body>
</html>
