@extends('reports.pdf.layout')

@section('content')
    <p style="text-align: center; font-size: 9px; color: #6b7280; margin-bottom: 10px;">
        Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
    </p>

    <!-- Summary -->
    <div style="text-align: center; margin-bottom: 12px; padding: 10px; background-color: {{ $netCashFlow >= 0 ? '#ecfdf5' : '#fef2f2' }}; border-radius: 4px; border: 1px solid {{ $netCashFlow >= 0 ? '#a7f3d0' : '#fecaca' }};">
        <p style="font-size: 9px; color: #6b7280; margin-bottom: 3px;">Net Cash Flow</p>
        <p style="font-size: 18px; font-weight: bold; color: {{ $netCashFlow >= 0 ? '#2E7D32' : '#C62828' }};">
            {{ $netCashFlow >= 0 ? '+' : '-' }}{{ number_format(abs($netCashFlow), 2) }}
        </p>
        <p style="font-size: 8px; color: #6b7280;">{{ $netCashFlow >= 0 ? 'Positive cash flow' : 'Negative cash flow' }}</p>
    </div>

    <!-- Cash Inflows Section -->
    <div class="section-title">Cash Inflows</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Payments Received from Customers</td>
                <td class="text-right positive">+{{ number_format($paymentsReceived, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td><strong>Total Cash Inflows</strong></td>
                <td class="text-right positive"><strong>+{{ number_format($totalInflows, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Cash Outflows Section -->
    <div class="section-title">Cash Outflows</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Payments Made to Vendors</td>
                <td class="text-right negative">-{{ number_format($paymentsMade, 2) }}</td>
            </tr>
            <tr>
                <td>Expenses Paid</td>
                <td class="text-right negative">-{{ number_format($expensesPaid, 2) }}</td>
            </tr>
            <tr>
                <td>Payroll Paid</td>
                <td class="text-right negative">-{{ number_format($payrollPaid, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td><strong>Total Cash Outflows</strong></td>
                <td class="text-right negative"><strong>-{{ number_format($totalOutflows, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Net Cash Flow -->
    <div class="section-title">Net Cash Flow</div>
    <table>
        <tbody>
            <tr>
                <td>Total Cash Inflows</td>
                <td class="text-right positive">+{{ number_format($totalInflows, 2) }}</td>
            </tr>
            <tr>
                <td>Total Cash Outflows</td>
                <td class="text-right negative">-{{ number_format($totalOutflows, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td><strong>Net Cash Flow</strong></td>
                <td class="text-right"><strong>{{ $netCashFlow >= 0 ? '+' : '-' }}{{ number_format(abs($netCashFlow), 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
