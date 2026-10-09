<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund {{ $refund->refund_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            background: #fff;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #C62828;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-logo {
            max-height: 60px;
            max-width: 200px;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 12px;
            color: #6b7280;
        }
        
        .refund-title {
            text-align: right;
        }
        
        .refund-title h1 {
            font-size: 32px;
            font-weight: bold;
            color: #C62828;
            margin-bottom: 5px;
        }
        
        .refund-number {
            font-size: 16px;
            color: #6b7280;
        }
        
        .refund-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
        }
        
        .status-completed {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .status-pending {
            background-color: #fef9c3;
            color: #854d0e;
        }
        
        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            margin-bottom: 8px;
        }
        
        .info-section p {
            margin-bottom: 3px;
        }
        
        .info-section .name {
            font-weight: 600;
            font-size: 16px;
            color: #1f2937;
        }
        
        .amount-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .amount-label {
            font-size: 14px;
            color: #991b1b;
            margin-bottom: 5px;
        }
        
        .amount-value {
            font-size: 36px;
            font-weight: bold;
            color: #C62828;
        }
        
        .details-table {
            width: 100%;
            margin-bottom: 30px;
        }
        
        .details-table th,
        .details-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .details-table th {
            background-color: #f9fafb;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            font-weight: 600;
        }
        
        .details-table td {
            color: #374151;
        }
        
        .notes-section {
            background: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
        }
        
        .notes-section h4 {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 60px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #9ca3af;
            padding-top: 10px;
            margin-top: 50px;
        }
        
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .container {
                padding: 20px;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #C62828;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #b91c1c;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" data-print>Print Refund</button>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                @if($tenant->logo)
                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}" class="company-logo">
                @else
                    <div class="company-name">{{ $tenant->name }}</div>
                @endif
                <div class="company-details">
                    @if($tenant->address){{ $tenant->address }}<br>@endif
                    @if($tenant->city || $tenant->state)
                        {{ $tenant->city }}{{ $tenant->city && $tenant->state ? ', ' : '' }}{{ $tenant->state }} {{ $tenant->postal_code }}<br>
                    @endif
                    @if($tenant->phone)Phone: {{ $tenant->phone }}<br>@endif
                    @if($tenant->email){{ $tenant->email }}@endif
                </div>
            </div>
            <div class="refund-title">
                <h1>REFUND</h1>
                <div class="refund-number"># {{ $refund->refund_number }}</div>
                <div class="refund-status status-{{ $refund->status }}">
                    {{ ucfirst($refund->status) }}
                </div>
            </div>
        </div>

        <!-- Refund Amount -->
        <div class="amount-box">
            <div class="amount-label">Refund Amount</div>
            <div class="amount-value">{{ number_format($refund->amount, 2) }}</div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-section">
                <h3>Refunded To</h3>
                <p class="name">{{ $refund->customer->name }}</p>
                @if($refund->customer->company_name)
                    <p>{{ $refund->customer->company_name }}</p>
                @endif
                @if($refund->customer->email)
                    <p>{{ $refund->customer->email }}</p>
                @endif
                @if($refund->customer->phone)
                    <p>{{ $refund->customer->phone }}</p>
                @endif
            </div>
            <div class="info-section">
                <h3>Refund Details</h3>
                <p><strong>Date:</strong> {{ $refund->refund_date->format('F d, Y') }}</p>
                <p><strong>Method:</strong> {{ \App\Models\InvoiceRefund::METHODS[$refund->refund_method] ?? ucfirst($refund->refund_method) }}</p>
                @if($refund->reference)
                    <p><strong>Reference:</strong> {{ $refund->reference }}</p>
                @endif
            </div>
        </div>

        <!-- Refund Details Table -->
        <table class="details-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Refund for Invoice {{ $refund->invoice->invoice_number }}</strong><br>
                        <span style="color: #6b7280; font-size: 12px;">
                            Original Invoice Date: {{ $refund->invoice->invoice_date->format('M d, Y') }}
                        </span>
                        @if($refund->reason)
                            <br>
                            <span style="color: #6b7280; font-size: 12px;">
                                Reason: {{ \App\Models\InvoiceRefund::REASONS[$refund->reason] ?? $refund->reason }}
                            </span>
                        @endif
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #C62828;">
                        {{ number_format($refund->amount, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Notes -->
        @if($refund->notes)
        <div class="notes-section">
            <h4>Notes</h4>
            <p>{{ $refund->notes }}</p>
        </div>
        @endif

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">
                    Authorized Signature
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    Customer Acknowledgment
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This refund was processed on {{ $refund->approved_at ? $refund->approved_at->format('F d, Y') : $refund->created_at->format('F d, Y') }}</p>
            @if($refund->createdBy)
                <p>Processed by: {{ $refund->createdBy->name }}</p>
            @endif
        </div>
    </div>
    @include('partials.dom-actions')
</body>
</html>
