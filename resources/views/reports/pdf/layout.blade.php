<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #374151;
            padding: 15px;
        }
        .header {
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid {{ config('brand.brand.600') }};
        }
        .header-content {
            width: 100%;
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
            width: 60px;
        }
        .logo-section img {
            max-width: 50px;
            max-height: 50px;
        }
        .company-section {
            padding-left: 12px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 2px;
        }
        .company-details {
            font-size: 8px;
            color: #6b7280;
            line-height: 1.4;
        }
        .report-section {
            text-align: right;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            color: {{ config('brand.brand.600') }};
            margin-bottom: 2px;
        }
        .report-date {
            font-size: 8px;
            color: #6b7280;
        }
        .filters {
            background-color: #f3f4f6;
            padding: 8px 10px;
            margin-bottom: 12px;
            border-radius: 4px;
            border-left: 3px solid {{ config('brand.brand.600') }};
        }
        .filters p {
            font-size: 8px;
            color: #4b5563;
            margin-bottom: 2px;
        }
        .filters p:last-child {
            margin-bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        th, td {
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8px;
        }
        th {
            background-color: {{ config('brand.brand.600') }};
            color: white;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .summary-row {
            background-color: {{ config('brand.brand.100') }} !important;
            font-weight: bold;
        }
        .total-row {
            background-color: {{ config('brand.brand.900') }} !important;
            color: white;
            font-weight: bold;
        }
        .positive {
            color: {{ config('brand.status.success') }};
        }
        .negative {
            color: {{ config('brand.status.danger') }};
        }
        .total-row .positive {
            color: #A5D6A7;
        }
        .total-row .negative {
            color: #FFCDD2;
        }
        /* Page numbers: dompdf fills in counter(page). */
        .page-number:after {
            content: "Page " counter(page);
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 7px;
            color: #9ca3af;
            text-align: center;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #1f2937;
            margin: 12px 0 8px 0;
            padding: 6px 10px;
            background-color: #f3f4f6;
            border-left: 3px solid {{ config('brand.brand.600') }};
            border-radius: 0 4px 4px 0;
        }
        .summary-card {
            display: inline-block;
            width: 30%;
            padding: 8px;
            margin: 3px;
            background-color: #f9fafb;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }
        .summary-card .label {
            font-size: 7px;
            color: #6b7280;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .summary-card .value {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
        }
        .highlight-box {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 12px;
            text-align: center;
        }
        .highlight-box.loss {
            background-color: #fef2f2;
            border-color: #fecaca;
        }
        @page {
            margin: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <table class="header-table">
                <tr>
                    @if(!empty($companyLogo))
                    <td class="logo-section">
                        <img src="{{ $companyLogo }}" alt="Logo">
                    </td>
                    @endif
                    <td class="company-section">
                        <div class="company-name">{{ $companyName }}</div>
                        <div class="company-details">
                            @if(!empty($companyEmail)){{ $companyEmail }}@endif
                            @if(!empty($companyEmail) && !empty($companyPhone)) | @endif
                            @if(!empty($companyPhone)){{ $companyPhone }}@endif
                            @if(!empty($companyAddress))<br>{{ $companyAddress }}@endif
                        </div>
                    </td>
                    <td class="report-section">
                        <div class="report-title">{{ $reportTitle }}</div>
                        <div class="report-date">{{ $generatedAt }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    @if(!empty($filters) && count(array_filter($filters)))
    <div class="filters">
        @foreach($filters as $key => $value)
            @if($value)
                <p><strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</p>
            @endif
        @endforeach
    </div>
    @endif

    @yield('content')

    <div class="footer">
        <p>Generated by {{ config('app.name') }} | {{ $generatedAt }} | <span class="page-number"></span></p>
    </div>
</body>
</html>
