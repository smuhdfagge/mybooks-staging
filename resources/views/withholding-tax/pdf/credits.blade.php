@extends('reports.pdf.layout')

{{-- WHT credits from customers (tax pack 2). --}}
@section('content')
    <p style="font-size: 9px; margin-bottom: 8px;">
        Certificate received (can be claimed): {{ number_format($totals['received'], 2) }} &middot;
        Awaiting certificate: {{ number_format($totals['awaiting'], 2) }} &middot;
        Total: {{ number_format($totals['total'], 2) }}
    </p>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Customer TIN</th>
                <th>Payment no.</th>
                <th class="text-right">WHT</th>
                <th>Status</th>
                <th>Certificate no.</th>
                <th>Certificate date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($credits as $c)
                <tr>
                    <td>{{ $c->deducted_on->format('Y-m-d') }}</td>
                    <td>{{ $c->customer?->name }}</td>
                    <td>{{ $c->customer?->tax_number }}</td>
                    <td>{{ $c->payment?->payment_number }}</td>
                    <td class="text-right">{{ number_format((float) $c->amount, 2) }}</td>
                    <td>{{ $c->status === 'received' ? 'Received' : 'Awaiting' }}</td>
                    <td>{{ $c->certificate_number }}</td>
                    <td>{{ $c->certificate_date?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No WHT credits.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
