@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
        </p>
    </div>

    <!-- Summary Cards -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 31%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Gross Salary</p>
            <p style="font-size: 12px; font-weight: bold; color: #1f2937;">{{ number_format($totalGross, 2) }}</p>
        </div>
        <div style="float: left; width: 31%; margin-left: 3%; padding: 8px; background-color: #fef2f2; border-radius: 4px; text-align: center; border: 1px solid #fecaca;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Deductions</p>
            <p style="font-size: 12px; font-weight: bold; color: #dc2626;">{{ number_format($totalDeductions, 2) }}</p>
        </div>
        <div style="float: right; width: 31%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Net Salary</p>
            <p style="font-size: 12px; font-weight: bold; color: #059669;">{{ number_format($totalNet, 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Earnings Details -->
    <div class="section-title">Employee Earnings Details</div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Pay Date</th>
                <th class="text-right">Basic</th>
                <th class="text-right">Allowances</th>
                <th class="text-right">Overtime</th>
                <th class="text-right">Gross</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->employee->first_name ?? '' }} {{ $payroll->employee->last_name ?? '' }}</td>
                    <td>{{ $payroll->employee->department->name ?? 'N/A' }}</td>
                    <td>{{ $payroll->pay_date->format('M d, Y') }}</td>
                    <td class="text-right">{{ number_format($payroll->basic_salary, 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->allowances, 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->overtime_amount, 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->gross_salary, 2) }}</td>
                    <td class="text-right negative">{{ number_format($payroll->tax_deduction, 2) }}</td>
                    <td class="text-right negative">{{ number_format($payroll->total_deductions, 2) }}</td>
                    <td class="text-right positive">{{ number_format($payroll->net_salary, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">No earnings records found for the selected period.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($payrolls->sum('basic_salary'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalAllowances, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalOvertime, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalGross, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalTax, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalDeductions, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalNet, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
