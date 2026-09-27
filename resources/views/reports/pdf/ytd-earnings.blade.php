@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Year: {{ $year }} ({{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }})
        </p>
    </div>

    <!-- Grand Totals -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 23%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">YTD Gross</p>
            <p style="font-size: 12px; font-weight: bold; color: #1f2937;">{{ number_format($grandTotals['gross'], 2) }}</p>
        </div>
        <div style="float: left; width: 23%; margin-left: 2%; padding: 8px; background-color: #fef2f2; border-radius: 4px; text-align: center; border: 1px solid #fecaca;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">YTD Tax</p>
            <p style="font-size: 12px; font-weight: bold; color: #dc2626;">{{ number_format($grandTotals['tax'], 2) }}</p>
        </div>
        <div style="float: left; width: 23%; margin-left: 2%; padding: 8px; background-color: #ecfdf5; border-radius: 4px; text-align: center; border: 1px solid #a7f3d0;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">YTD Net</p>
            <p style="font-size: 12px; font-weight: bold; color: #059669;">{{ number_format($grandTotals['net'], 2) }}</p>
        </div>
        <div style="float: right; width: 23%; padding: 8px; background-color: #eff6ff; border-radius: 4px; text-align: center; border: 1px solid #bfdbfe;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Employees</p>
            <p style="font-size: 12px; font-weight: bold; color: #2563eb;">{{ $byEmployee->count() }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- YTD Earnings by Employee -->
    <div class="section-title">Year-to-Date Earnings by Employee</div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th class="text-center">Periods</th>
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
            @forelse($byEmployee as $record)
                <tr>
                    <td>{{ $record['employee']->first_name }} {{ $record['employee']->last_name }}</td>
                    <td>{{ $record['employee']->department->name ?? 'N/A' }}</td>
                    <td class="text-center">{{ $record['pay_periods'] }}</td>
                    <td class="text-right">{{ number_format($record['ytd_basic'], 2) }}</td>
                    <td class="text-right">{{ number_format($record['ytd_allowances'], 2) }}</td>
                    <td class="text-right">{{ number_format($record['ytd_overtime'], 2) }}</td>
                    <td class="text-right">{{ number_format($record['ytd_gross'], 2) }}</td>
                    <td class="text-right negative">{{ number_format($record['ytd_tax'], 2) }}</td>
                    <td class="text-right negative">{{ number_format($record['ytd_total_deductions'], 2) }}</td>
                    <td class="text-right positive">{{ number_format($record['ytd_net'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">No payroll records found for the selected year.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3"><strong>Grand Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($grandTotals['basic'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($grandTotals['allowances'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($grandTotals['overtime'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($grandTotals['gross'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($grandTotals['tax'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($grandTotals['deductions'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($grandTotals['net'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
