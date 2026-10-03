@extends('reports.pdf.layout')

@section('content')
    <p style="text-align: center; font-size: 9px; color: #6b7280; margin-bottom: 10px;">
        Payments from {{ \Carbon\Carbon::parse($filters['start_date'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($filters['end_date'])->format('d M Y') }}
    </p>

    <div class="section-title">By customer</div>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th>TIN</th>
                <th class="text-right">Awaiting</th>
                <th class="text-right">Received</th>
                <th class="text-right">Utilised</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byCustomer as $row)
                <tr>
                    <td>{{ $row['customer'] }}</td>
                    <td>{{ $row['tin'] }}</td>
                    <td class="text-right">{{ number_format($row['outstanding'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['received'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['utilised'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">No WHT deducted by customers in this period.</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2"><strong>Total</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['outstanding'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['received'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['utilised'], 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totals['total'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Payments</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Payment</th>
                <th>Customer</th>
                <th>Invoice</th>
                <th>Type</th>
                <th class="text-right">Before VAT</th>
                <th class="text-right">Rate %</th>
                <th class="text-right">WHT</th>
                <th>Status</th>
                <th>Credit note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->payment_date?->format('d M Y') }}</td>
                    <td>{{ $payment->payment_number }}</td>
                    <td>{{ $payment->customer?->name }}</td>
                    <td>{{ $payment->invoice?->invoice_number }}</td>
                    <td>{{ $payment->whtCategory?->name }}</td>
                    <td class="text-right">{{ number_format((float) $payment->wht_base, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $payment->wht_rate, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $payment->wht_amount, 2) }}</td>
                    <td>{{ \App\Models\PaymentReceived::WHT_STATUS_LABELS[$payment->whtStatus()] ?? '' }}</td>
                    <td>{{ $payment->wht_credit_note_number }} {{ $payment->wht_credit_note_date?->format('d M Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
