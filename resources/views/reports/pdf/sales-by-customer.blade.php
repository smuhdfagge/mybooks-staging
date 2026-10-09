@extends('reports.pdf.layout')

@section('content')
    <p style="text-align: center; font-size: 9px; color: #6b7280; margin-bottom: 10px;">
        Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
    </p>

    <!-- Summary Cards -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 48%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Sales</p>
            <p style="font-size: 14px; font-weight: bold; color: #2E7D32;">{{ number_format($totalSales, 2) }}</p>
        </div>
        <div style="float: right; width: 48%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Amount Paid</p>
            <p style="font-size: 14px; font-weight: bold; color: #1f2937;">{{ number_format($totalPaid, 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Sales by Customer Table -->
    <div class="section-title">Sales by Customer</div>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th>Company</th>
                <th class="text-center">Invoices</th>
                <th class="text-right">Total Sales</th>
                <th class="text-right">Amount Paid</th>
                <th class="text-right">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->company_name ?? '-' }}</td>
                    <td class="text-center">{{ $customer->invoices_count }}</td>
                    <td class="text-right">{{ number_format($customer->invoices_sum_total ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($customer->invoices_sum_amount_paid ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format(($customer->invoices_sum_total ?? 0) - ($customer->invoices_sum_amount_paid ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No sales found for the selected period.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($totalSales, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalPaid, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalSales - $totalPaid, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
