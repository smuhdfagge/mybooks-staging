@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
        </p>
    </div>

    <!-- Summary Cards -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 48%; padding: 8px; background-color: #fef2f2; border-radius: 4px; text-align: center; border: 1px solid #fecaca;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Purchases</p>
            <p style="font-size: 14px; font-weight: bold; color: #dc2626;">{{ number_format($totalPurchases, 2) }}</p>
        </div>
        <div style="float: right; width: 48%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Amount Paid</p>
            <p style="font-size: 14px; font-weight: bold; color: #1f2937;">{{ number_format($totalPaid, 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Purchase by Vendor Table -->
    <div class="section-title">Purchases by Vendor</div>
    <table>
        <thead>
            <tr>
                <th>Vendor</th>
                <th>Company</th>
                <th class="text-center">Bills</th>
                <th class="text-right">Total Purchases</th>
                <th class="text-right">Amount Paid</th>
                <th class="text-right">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vendors as $vendor)
                <tr>
                    <td>{{ $vendor->name }}</td>
                    <td>{{ $vendor->company_name ?? '-' }}</td>
                    <td class="text-center">{{ $vendor->bills_count }}</td>
                    <td class="text-right">{{ number_format($vendor->bills_sum_total ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($vendor->bills_sum_amount_paid ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format(($vendor->bills_sum_total ?? 0) - ($vendor->bills_sum_amount_paid ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No purchases found for the selected period.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($totalPurchases, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalPaid, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalPurchases - $totalPaid, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
