@extends('reports.pdf.layout')

@section('content')
    <p style="text-align: center; font-size: 9px; color: #6b7280; margin-bottom: 10px;">
        WHT deducted at source in {{ $month->format('F Y') }}.
        Payer: {{ $payer?->name }}@if($payer?->tax_number), TIN {{ $payer->tax_number }}@endif
    </p>

    @forelse($groups as $group)
        <div class="section-title">{{ $group['label'] }} (due by {{ $group['due']->format('j M Y') }})</div>
        <table>
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>TIN</th>
                    <th>Address</th>
                    <th>Transaction type</th>
                    <th>Date</th>
                    <th>Payment</th>
                    <th>Bill</th>
                    <th class="text-right">Before VAT</th>
                    <th class="text-right">Rate %</th>
                    <th class="text-right">WHT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group['rows'] as $payment)
                    <tr>
                        <td>{{ $payment->vendor?->name }}</td>
                        <td>{{ $payment->vendor?->tax_number ?: 'None' }}</td>
                        <td>{{ collect([$payment->vendor?->address, $payment->vendor?->city, $payment->vendor?->state])->filter()->implode(', ') }}</td>
                        <td>{{ $payment->whtCategory?->name }}</td>
                        <td>{{ $payment->payment_date->format('d M Y') }}</td>
                        <td>{{ $payment->payment_number }}</td>
                        <td>{{ $payment->bill?->bill_number }}</td>
                        <td class="text-right">{{ number_format((float) $payment->wht_base, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $payment->wht_rate, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $payment->wht_amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="7"><strong>Total deducted</strong></td>
                    <td class="text-right"><strong>{{ number_format((float) $group['rows']->sum('wht_base'), 2) }}</strong></td>
                    <td></td>
                    <td class="text-right"><strong>{{ number_format($group['deducted'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <table style="margin-top: 6px;">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>TIN</th>
                    <th class="text-right">Payments</th>
                    <th class="text-right">Before VAT</th>
                    <th class="text-right">WHT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group['vendors'] as $vendor)
                    <tr>
                        <td>{{ $vendor['name'] }}</td>
                        <td>{{ $vendor['tin'] ?: 'None' }}</td>
                        <td class="text-right">{{ $vendor['payments'] }}</td>
                        <td class="text-right">{{ number_format($vendor['base'], 2) }}</td>
                        <td class="text-right">{{ number_format($vendor['wht'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4">Paid over for the month</td>
                    <td class="text-right">{{ number_format($group['remitted'], 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="4"><strong>Still to pay</strong></td>
                    <td class="text-right"><strong>{{ number_format($group['outstanding'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @empty
        <p>No WHT was deducted from vendors in {{ $month->format('F Y') }}.</p>
    @endforelse
@endsection
