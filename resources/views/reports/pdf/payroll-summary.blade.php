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
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Deductions</p>
            <p style="font-size: 12px; font-weight: bold; color: #C62828;">{{ number_format($totalDeductions, 2) }}</p>
        </div>
        <div style="float: right; width: 31%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Net Salary</p>
            <p style="font-size: 12px; font-weight: bold; color: #2E7D32;">{{ number_format($totalNet, 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Payroll by Employee -->
    <div class="section-title">Payroll by Employee</div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Employee ID</th>
                <th class="text-center">Pay Periods</th>
                <th class="text-right">Gross Salary</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">Net Salary</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byEmployee as $record)
                <tr>
                    <td>{{ $record['employee']->first_name }} {{ $record['employee']->last_name }}</td>
                    <td>{{ $record['employee']->employee_id ?? '-' }}</td>
                    <td class="text-center">{{ $record['count'] }}</td>
                    <td class="text-right">{{ number_format($record['gross'], 2) }}</td>
                    <td class="text-right negative">{{ number_format($record['deductions'], 2) }}</td>
                    <td class="text-right positive">{{ number_format($record['net'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No payroll records found for the selected period.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($totalGross, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalDeductions, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalNet, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Payroll Details -->
    <div class="section-title">Payroll Details</div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Pay Date</th>
                <th>Period</th>
                <th class="text-right">Gross</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">Net</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->employee->first_name ?? '' }} {{ $payroll->employee->last_name ?? '' }}</td>
                    <td>{{ $payroll->pay_date?->format('Y-m-d') }}</td>
                    <td style="font-size: 7px;">{{ $payroll->pay_period_start?->format('M d') }} - {{ $payroll->pay_period_end?->format('M d, Y') }}</td>
                    <td class="text-right">{{ number_format($payroll->gross_salary, 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->total_deductions, 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->net_salary, 2) }}</td>
                    <td>{{ ucfirst($payroll->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No payroll records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
