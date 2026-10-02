@extends('reports.pdf.layout')

{{-- Remittance schedule for one statutory body (tax pack 1). --}}
@section('content')
    <p style="font-size: 9px; margin-bottom: 8px;">
        <strong>{{ $schedule['title'] }}</strong>
        @if($employerTin) &middot; Employer TIN: {{ $employerTin }} @endif
        &middot; Due by {{ $dueDate->format('j F Y') }}
    </p>

    @forelse($schedule['groups'] as $label => $group)
        @if($schedule['group_label'])
            <div class="section-title">{{ $schedule['group_label'] }}: {{ $label }}</div>
        @endif
        <table>
            <thead>
                <tr>
                    @foreach($schedule['columns'] as $key => $heading)
                        <th @class(['text-right' => in_array($key, \App\Services\Payroll\StatutorySchedule::MONEY, true)])>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($group['rows'] as $row)
                    <tr>
                        @foreach($schedule['columns'] as $key => $heading)
                            @if(in_array($key, \App\Services\Payroll\StatutorySchedule::MONEY, true))
                                <td class="text-right">{{ number_format($row[$key], 2) }}</td>
                            @else
                                <td>{{ $row[$key] ?? '' }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                <tr class="total-row">
                    @foreach($schedule['columns'] as $key => $heading)
                        @if(in_array($key, \App\Services\Payroll\StatutorySchedule::MONEY, true))
                            <td class="text-right"><strong>{{ number_format($group['totals'][$key], 2) }}</strong></td>
                        @else
                            <td>@if($loop->first)<strong>Total{{ $schedule['group_label'] ? ' '.$label : '' }}</strong>@endif</td>
                        @endif
                    @endforeach
                </tr>
            </tbody>
        </table>
    @empty
        <p>Nothing to remit for this period. Only approved or paid payroll is counted.</p>
    @endforelse

    @if(count($schedule['groups']) > 1)
        <p style="margin-top: 10px; font-size: 10px;"><strong>Grand total: {{ number_format($schedule['total'], 2) }}</strong></p>
    @endif
@endsection
