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
            <p style="font-size: 12px; font-weight: bold; color: #dc2626;">{{ number_format($totalDeductions, 2) }}</p>
        </div>
        <div style="float: right; width: 31%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Net Salary</p>
            <p style="font-size: 12px; font-weight: bold; color: #059669;">{{ number_format($totalNet, 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Department Breakdown -->
    <div class="section-title">Department Breakdown</div>
    <table>
        <thead>
            <tr>
                <th>Department</th>
                <th class="text-center">Employees</th>
                <th class="text-right">Gross Salary</th>
                <th class="text-right">Allowances</th>
                <th class="text-right">Overtime</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">Net Salary</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byDepartment as $record)
                <tr>
                    <td>{{ $record['department_name'] }}</td>
                    <td class="text-center">{{ $record['employee_count'] }}</td>
                    <td class="text-right">{{ number_format($record['gross'], 2) }}</td>
                    <td class="text-right">{{ number_format($record['allowances'], 2) }}</td>
                    <td class="text-right">{{ number_format($record['overtime'], 2) }}</td>
                    <td class="text-right negative">{{ number_format($record['tax'], 2) }}</td>
                    <td class="text-right negative">{{ number_format($record['deductions'], 2) }}</td>
                    <td class="text-right positive">{{ number_format($record['net'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">No payroll records found for the selected period.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($totalGross, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($byDepartment->sum('allowances'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($byDepartment->sum('overtime'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($byDepartment->sum('tax'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalDeductions, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalNet, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
