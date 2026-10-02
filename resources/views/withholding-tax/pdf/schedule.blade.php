@extends('reports.pdf.layout')

{{-- Schedule of WHT deducted from vendors in a month (tax pack 2). --}}
@section('content')
    <p style="font-size: 9px; margin-bottom: 8px;">Due by {{ $dueDate->format('j F Y') }}. WHT deducted from individuals is paid to their State IRS.</p>

    @forelse($groups as $vendor => $rows)
        <div class="section-title">{{ $vendor }} @if($rows->first()['tin']) &middot; TIN {{ $rows->first()['tin'] }} @else &middot; no TIN @endif &middot; {{ $rows->first()['type'] }}</div>
        <table>
            <thead>
                <tr>
                    <th>Payment date</th>
                    <th>Payment no.</th>
                    <th>Nature of transaction</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Rate %</th>
                    <th class="text-right">WHT deducted</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['payment'] }}</td>
                        <td>{{ $row['transaction'] }}</td>
                        <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['rate'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['wht'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="5"><strong>Total</strong></td>
                    <td class="text-right"><strong>{{ number_format(collect($rows)->sum('wht'), 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @empty
        <p>No WHT was deducted in this month.</p>
    @endforelse

    <p style="margin-top: 10px; font-size: 10px;"><strong>Total WHT for the month: {{ number_format($total, 2) }}</strong></p>
@endsection
