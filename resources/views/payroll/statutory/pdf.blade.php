@extends('reports.pdf.layout')

@section('content')
    @php($left = ['employee', 'staff_no', 'tin', 'rsa_pin', 'nhf_number'])
    @foreach($data['groups'] as $group)
        <div class="section-title">{{ $group['label'] }}</div>
        <table>
            <thead>
                <tr>
                    @foreach($data['columns'] as $key => $label)
                        <th class="{{ in_array($key, $left) ? '' : 'text-right' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($group['rows'] as $row)
                    <tr>
                        @foreach($data['columns'] as $key => $label)
                            @if(in_array($key, $left))
                                <td>{{ $row[$key] ?? '' }}</td>
                            @else
                                <td class="text-right">{{ number_format((float) ($row[$key] ?? 0), 2) }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="{{ count($data['columns']) - 1 }}"><strong>Total {{ $group['label'] }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($group['amount'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <div class="section-title">Summary</div>
    <table>
        <tbody>
            <tr><td>Schedule total</td><td class="text-right"><strong>{{ number_format($data['total'], 2) }}</strong></td></tr>
            <tr><td>Remitted</td><td class="text-right">{{ number_format($data['remitted'], 2) }}</td></tr>
            <tr><td>Outstanding</td><td class="text-right">{{ number_format($data['outstanding'], 2) }}</td></tr>
            <tr><td>Ledger {{ $data['ledger']['account_code'] }} {{ $data['ledger']['account_name'] }}, posted in {{ $data['month']->format('F Y') }}</td><td class="text-right">{{ number_format($data['ledger']['posted'], 2) }}</td></tr>
            <tr><td>Difference</td><td class="text-right">{{ number_format($data['ledger']['difference'], 2) }}</td></tr>
            <tr><td>Due</td><td class="text-right">{{ $data['due_date']->format('j F Y') }} ({{ $data['due_rule'] }})</td></tr>
            @if(isset($data['extra']['year_to_date']))
                <tr><td>ITF for {{ $data['month']->format('Y') }} so far</td><td class="text-right">{{ number_format($data['extra']['year_to_date'], 2) }}</td></tr>
            @endif
        </tbody>
    </table>
@endsection
