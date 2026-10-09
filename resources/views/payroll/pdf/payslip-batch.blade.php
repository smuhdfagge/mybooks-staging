<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslips - Batch {{ $batchNumber }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #374151;
            padding: 20px;
        }
        .payslip-container {
            border: 2px solid #1F4E79;
            border-radius: 6px;
            overflow: hidden;
            page-break-after: always;
        }
        .payslip-container:last-child {
            page-break-after: auto;
        }
        /* Header */
        .payslip-header {
            background: #1F4E79;
            color: white;
            padding: 16px 20px;
        }
        .header-table {
            width: 100%;
            border: none;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }
        .logo-section {
            width: 55px;
        }
        .logo-section img {
            max-width: 48px;
            max-height: 48px;
            border-radius: 4px;
        }
        .company-section {
            padding-left: 12px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .company-details {
            font-size: 8px;
            opacity: 0.9;
            line-height: 1.4;
        }
        .payslip-title-section {
            text-align: right;
        }
        .payslip-title {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }
        .payslip-number {
            font-size: 9px;
            opacity: 0.9;
        }
        /* Body */
        .payslip-body {
            padding: 16px 20px;
        }
        .info-grid {
            width: 100%;
            border: none;
            margin-bottom: 16px;
        }
        .info-grid td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .info-block {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 12px;
        }
        .info-block-title {
            font-size: 8px;
            font-weight: bold;
            color: #1F4E79;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row {
            margin-bottom: 3px;
        }
        .info-label {
            font-size: 8px;
            color: #6b7280;
        }
        .info-value {
            font-size: 9px;
            font-weight: 600;
            color: #1f2937;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #1f2937;
            padding: 6px 10px;
            background-color: #f1f5f9;
            border-left: 3px solid #1F4E79;
            border-radius: 0 4px 4px 0;
            margin-bottom: 8px;
        }
        table.breakdown {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.breakdown th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 10px;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        table.breakdown th.text-right {
            text-align: right;
        }
        table.breakdown td {
            padding: 5px 10px;
            font-size: 9px;
            border-bottom: 1px solid #f1f5f9;
        }
        table.breakdown td.text-right {
            text-align: right;
        }
        table.breakdown tr.subtotal td {
            font-weight: bold;
            border-top: 2px solid #e2e8f0;
            border-bottom: none;
            padding-top: 8px;
            font-size: 9px;
        }
        .amount-positive {
            color: #2E7D32;
            font-weight: 600;
        }
        .amount-negative {
            color: #C62828;
            font-weight: 600;
        }
        .net-pay-box {
            background: #2E7D32;
            color: white;
            border-radius: 6px;
            padding: 14px 20px;
            margin: 16px 0 12px 0;
            text-align: center;
        }
        .net-pay-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        .net-pay-amount {
            font-size: 22px;
            font-weight: bold;
        }
        .payslip-footer {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
        .footer-note {
            font-size: 7px;
            color: #9ca3af;
            text-align: center;
            font-style: italic;
        }
        .generated-at {
            font-size: 7px;
            color: #9ca3af;
            text-align: center;
            margin-top: 4px;
        }
        @page {
            margin: 15px;
        }
    </style>
</head>
<body>
    @foreach($payslips as $slip)
    @php $payroll = $slip['payroll']; $employee = $slip['employee']; @endphp
    <div class="payslip-container">
        <!-- Header -->
        <div class="payslip-header">
            <table class="header-table">
                <tr>
                    @if(!empty($slip['companyLogo']))
                    <td class="logo-section">
                        <img src="{{ $slip['companyLogo'] }}" alt="Logo">
                    </td>
                    @endif
                    <td class="company-section">
                        <div class="company-name">{{ $slip['companyName'] }}</div>
                        <div class="company-details">
                            @if(!empty($slip['companyEmail'])){{ $slip['companyEmail'] }}@endif
                            @if(!empty($slip['companyEmail']) && !empty($slip['companyPhone'])) | @endif
                            @if(!empty($slip['companyPhone'])){{ $slip['companyPhone'] }}@endif
                            @if(!empty($slip['companyAddress']))<br>{{ $slip['companyAddress'] }}@endif
                        </div>
                    </td>
                    <td class="payslip-title-section">
                        <div class="payslip-title">PAYSLIP</div>
                        <div class="payslip-number">{{ $payroll->payroll_number }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Body -->
        <div class="payslip-body">
            <!-- Employee & Pay Period Info -->
            <table class="info-grid">
                <tr>
                    <td style="width: 49%; padding-right: 8px;">
                        <div class="info-block">
                            <div class="info-block-title">Employee Details</div>
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <span class="info-value">{{ $employee->first_name ?? '' }} {{ $employee->last_name ?? '' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Employee ID:</span>
                                <span class="info-value">{{ $employee->employee_id ?? '-' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Department:</span>
                                <span class="info-value">{{ $employee->department->name ?? '-' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Designation:</span>
                                <span class="info-value">{{ $employee->designation->title ?? $employee->designation->name ?? '-' }}</span>
                            </div>
                        </div>
                    </td>
                    <td style="width: 49%; padding-left: 8px;">
                        <div class="info-block">
                            <div class="info-block-title">Pay Period</div>
                            <div class="info-row">
                                <span class="info-label">Period:</span>
                                <span class="info-value">{{ $payroll->pay_period_start->format('M d, Y') }} - {{ $payroll->pay_period_end->format('M d, Y') }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Pay Date:</span>
                                <span class="info-value">{{ $payroll->pay_date->format('M d, Y') }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Payment Method:</span>
                                <span class="info-value">{{ ucfirst($payroll->payment_method ?? 'N/A') }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <span class="info-value">{{ ucfirst($payroll->status) }}</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Earnings & Deductions side by side -->
            <table class="info-grid">
                <tr>
                    <td style="width: 49%; padding-right: 8px; vertical-align: top;">
                        <div class="section-title">Earnings</div>
                        <table class="breakdown">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Basic Salary</td>
                                    <td class="text-right">{{ number_format($payroll->basic_salary, 2) }}</td>
                                </tr>
                                @if(!empty($payroll->allowance_details))
                                    @foreach($payroll->allowance_details as $allowance)
                                        <tr>
                                            <td>
                                                {{ $allowance['name'] }}
                                                @if(($allowance['amount_type'] ?? '') === 'percentage')
                                                    <span style="color: #9ca3af; font-size: 7px;">({{ $allowance['rate'] }}%)</span>
                                                @endif
                                            </td>
                                            <td class="text-right amount-positive">{{ number_format($allowance['amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                @elseif($payroll->allowances > 0)
                                    <tr>
                                        <td>Allowances</td>
                                        <td class="text-right amount-positive">{{ number_format($payroll->allowances, 2) }}</td>
                                    </tr>
                                @endif
                                @if($payroll->overtime_amount > 0)
                                    <tr>
                                        <td>Overtime ({{ number_format($payroll->overtime_hours, 1) }} hrs)</td>
                                        <td class="text-right amount-positive">{{ number_format($payroll->overtime_amount, 2) }}</td>
                                    </tr>
                                @endif
                                <tr class="subtotal">
                                    <td>Gross Salary</td>
                                    <td class="text-right" style="color: #2E7D32;">{{ number_format($payroll->gross_salary, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                    <td style="width: 49%; padding-left: 8px; vertical-align: top;">
                        <div class="section-title">Deductions</div>
                        <table class="breakdown">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($payroll->tax_deduction > 0)
                                    <tr>
                                        <td>Tax</td>
                                        <td class="text-right amount-negative">{{ number_format($payroll->tax_deduction, 2) }}</td>
                                    </tr>
                                @endif
                                @if(!empty($payroll->deduction_details))
                                    @foreach($payroll->deduction_details as $deduction)
                                        @if(str_starts_with($deduction['name'] ?? '', '_'))
                                            @continue
                                        @endif
                                        <tr>
                                            <td>
                                                {{ $deduction['name'] }}
                                                @if(($deduction['amount_type'] ?? '') === 'percentage')
                                                    <span style="color: #9ca3af; font-size: 7px;">({{ $deduction['rate'] }}%)</span>
                                                @endif
                                            </td>
                                            <td class="text-right amount-negative">{{ number_format($deduction['amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                @elseif($payroll->other_deductions > 0)
                                    <tr>
                                        <td>Other Deductions</td>
                                        <td class="text-right amount-negative">{{ number_format($payroll->other_deductions, 2) }}</td>
                                    </tr>
                                @endif
                                @if($payroll->total_deductions == 0)
                                    <tr>
                                        <td colspan="2" style="color: #9ca3af; text-align: center; font-style: italic;">No deductions</td>
                                    </tr>
                                @endif
                                <tr class="subtotal">
                                    <td>Total Deductions</td>
                                    <td class="text-right" style="color: #C62828;">{{ number_format($payroll->total_deductions, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Net Pay -->
            <div class="net-pay-box">
                <div class="net-pay-label">Net Pay</div>
                <div class="net-pay-amount">{{ number_format($payroll->net_salary, 2) }}</div>
            </div>

            @if($payroll->notes)
                <div style="margin-top: 10px; padding: 8px 10px; background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 4px;">
                    <div style="font-size: 8px; font-weight: bold; color: #92400e; margin-bottom: 3px;">Notes</div>
                    <div style="font-size: 8px; color: #78350f;">{{ $payroll->notes }}</div>
                </div>
            @endif

            <!-- Footer -->
            <div class="payslip-footer">
                <p class="footer-note">This is a computer-generated payslip and does not require a signature.</p>
                <p class="generated-at">Generated on {{ $slip['generatedAt'] }} by {{ config('app.name') }}</p>
            </div>
        </div>
    </div>
    @endforeach
</body>
</html>
