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
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Gross Salary</p>
            <p style="font-size: 12px; font-weight: bold; color: #1f2937;">{{ number_format($totals['total_gross'], 2) }}</p>
        </div>
        <div style="float: left; width: 31%; margin-left: 3%; padding: 8px; background-color: #fef2f2; border-radius: 4px; text-align: center; border: 1px solid #fecaca;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Employer Contributions</p>
            <p style="font-size: 12px; font-weight: bold; color: #C62828;">{{ number_format($totals['total_employer_contributions'], 2) }}</p>
        </div>
        <div style="float: right; width: 31%; padding: 8px; background-color: #EEF3F8; border-radius: 4px; text-align: center; border: 1px solid #B4C8DD;">
            <p style="font-size: 8px; color: #6b7280; margin-bottom: 2px;">Total Cost to Company</p>
            <p style="font-size: 12px; font-weight: bold; color: #1F4E79;">{{ number_format($totals['total_cost'] ?? ($totals['total_gross'] + $totals['total_employer_contributions']), 2) }}</p>
        </div>
    </div>

    <div style="clear: both;"></div>

    @if($contributionTypes->count() > 0)
    <!-- Contribution Types -->
    <div class="section-title">Contribution Types</div>
    <table>
        <thead>
            <tr>
                <th>Contribution Type</th>
                <th class="text-center">Count</th>
                <th class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contributionTypes as $type)
                <tr>
                    <td>{{ $type['name'] }}</td>
                    <td class="text-center">{{ $type['count'] }}</td>
                    <td class="text-right">{{ number_format($type['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- By Employee -->
    <div class="section-title">Employer Contributions by Employee</div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th class="text-right">Gross Salary</th>
                <th class="text-right">Employer Contributions</th>
                <th class="text-right">Cost Ratio</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byEmployee as $record)
                <tr>
                    <td>{{ $record['employee']->first_name }} {{ $record['employee']->last_name }}</td>
                    <td>{{ $record['employee']->department->name ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($record['gross_salary'], 2) }}</td>
                    <td class="text-right negative">{{ number_format($record['employer_contributions'], 2) }}</td>
                    <td class="text-right">{{ $record['cost_ratio'] ?? 0 }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No employer contribution records found.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2"><strong>Totals</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['total_gross'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['total_employer_contributions'], 2) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endsection
