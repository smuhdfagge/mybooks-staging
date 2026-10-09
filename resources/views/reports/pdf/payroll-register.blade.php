@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Month: {{ \Carbon\Carbon::parse($startDate)->format('F Y') }}
        </p>
    </div>

    <!-- Summary -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 23%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Gross Salary</p>
            <p style="font-size: 12px; font-weight: bold; color: #1f2937;">{{ number_format($totals['gross_salary'], 2) }}</p>
        </div>
        <div style="float: left; width: 23%; margin-left: 2%; padding: 8px; background-color: #fef2f2; border-radius: 4px; text-align: center; border: 1px solid #fecaca;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Deductions</p>
            <p style="font-size: 12px; font-weight: bold; color: #C62828;">{{ number_format($totals['total_deductions'], 2) }}</p>
        </div>
        <div style="float: left; width: 23%; margin-left: 2%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Net Salary</p>
            <p style="font-size: 12px; font-weight: bold; color: #2E7D32;">{{ number_format($totals['net_salary'], 2) }}</p>
        </div>
        <div style="float: right; width: 23%; padding: 8px; background-color: #EEF3F8; border-radius: 4px; text-align: center; border: 1px solid #B4C8DD;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Employees</p>
            <p style="font-size: 12px; font-weight: bold; color: #1F4E79;">{{ $payrolls->count() }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Payroll Register -->
    <div class="section-title">Payroll Register</div>
    <table>
        <thead>
            <tr>
                <th>Payroll #</th>
                <th>Employee</th>
                <th>Department</th>
                <th class="text-right">Basic</th>
                <th class="text-right">Allowances</th>
                <th class="text-right">Overtime</th>
                <th class="text-right">Gross</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">Net</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->payroll_number }}</td>
                    <td>{{ ($payroll->employee->first_name ?? '') . ' ' . ($payroll->employee->last_name ?? '') }}</td>
                    <td>{{ $payroll->employee->department->name ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($payroll->basic_salary, 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->allowances, 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->overtime_amount, 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->gross_salary, 2) }}</td>
                    <td class="text-right negative">{{ number_format($payroll->tax_deduction, 2) }}</td>
                    <td class="text-right negative">{{ number_format($payroll->total_deductions, 2) }}</td>
                    <td class="text-right positive">{{ number_format($payroll->net_salary, 2) }}</td>
                    <td>{{ ucfirst($payroll->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center">No payroll records found.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['basic_salary'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['allowances'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['overtime_amount'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['gross_salary'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['tax_deduction'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['total_deductions'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['net_salary'], 2) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endsection
