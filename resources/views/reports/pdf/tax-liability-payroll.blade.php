@extends('reports.pdf.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <p style="font-size: 9px; color: #6b7280;">
            Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
        </p>
    </div>

    <!-- Summary -->
    <div style="margin-bottom: 12px; overflow: hidden;">
        <div style="float: left; width: 31%; padding: 8px; background-color: #fef2f2; border-radius: 4px; text-align: center; border: 1px solid #fecaca;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Tax Liability</p>
            <p style="font-size: 12px; font-weight: bold; color: #dc2626;">{{ number_format($totals['total_tax'], 2) }}</p>
        </div>
        <div style="float: left; width: 31%; margin-left: 3%; padding: 8px; background-color: #f3f4f6; border-radius: 4px; text-align: center; border: 1px solid #e5e7eb;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Taxable Income</p>
            <p style="font-size: 12px; font-weight: bold; color: #1f2937;">{{ number_format($totals['total_taxable'], 2) }}</p>
        </div>
        <div style="float: right; width: 31%; padding: 8px; background-color: #eff6ff; border-radius: 4px; text-align: center; border: 1px solid #bfdbfe;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Effective Rate / Employees</p>
            <p style="font-size: 12px; font-weight: bold; color: #2563eb;">{{ $totals['effective_rate'] ?? 0 }}% / {{ $totals['employee_count'] ?? 0 }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    <!-- Tax by Employee -->
    <div class="section-title">Tax Liability by Employee</div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th class="text-center">Pay Periods</th>
                <th class="text-right">Taxable Income</th>
                <th class="text-right">Tax Deducted</th>
                <th class="text-right">Effective Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byEmployee as $record)
                <tr>
                    <td>{{ $record['employee']->first_name }} {{ $record['employee']->last_name }}</td>
                    <td>{{ $record['employee']->department->name ?? 'N/A' }}</td>
                    <td class="text-center">{{ $record['pay_periods'] }}</td>
                    <td class="text-right">{{ number_format($record['taxable_income'], 2) }}</td>
                    <td class="text-right negative">{{ number_format($record['tax_deducted'], 2) }}</td>
                    <td class="text-right">{{ $record['effective_rate'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No tax records found for the selected period.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['total_taxable'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['total_tax'], 2) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endsection
