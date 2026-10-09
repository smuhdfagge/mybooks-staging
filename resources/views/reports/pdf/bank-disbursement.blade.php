@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
        </p>
    </div>

    <!-- Summary -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 31%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Payments</p>
            <p style="font-size: 12px; font-weight: bold; color: #1f2937;">{{ $totals['count'] }}</p>
        </div>
        <div style="float: left; width: 31%; margin-left: 3%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Net Amount</p>
            <p style="font-size: 12px; font-weight: bold; color: #2E7D32;">{{ number_format($totals['total_net'], 2) }}</p>
        </div>
        <div style="float: right; width: 31%; padding: 8px; background-color: #EEF3F8; border-radius: 4px; text-align: center; border: 1px solid #B4C8DD;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Gross Amount</p>
            <p style="font-size: 12px; font-weight: bold; color: #1F4E79;">{{ number_format($totals['total_gross'] ?? 0, 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Disbursement Details -->
    <div class="section-title">Bank Disbursement Details</div>
    <table>
        <thead>
            <tr>
                <th>Payroll #</th>
                <th>Employee</th>
                <th>Bank Account</th>
                <th>Payment Method</th>
                <th class="text-right">Net Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->payroll_number }}</td>
                    <td>{{ ($payroll->employee->first_name ?? '') . ' ' . ($payroll->employee->last_name ?? '') }}</td>
                    <td>****{{ substr($payroll->employee->bank_account_number ?? '0000', -4) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $payroll->payment_method ?? 'N/A')) }}</td>
                    <td class="text-right positive">{{ number_format($payroll->net_salary, 2) }}</td>
                    <td>{{ ucfirst($payroll->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No disbursement records found.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="4"><strong>Total</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['total_net'], 2) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endsection
