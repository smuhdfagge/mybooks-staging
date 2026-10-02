@extends('documents.layout', ['title' => 'QUOTATION', 'number' => $quotation->quotation_number])

@php($symbol = $tenant->currency_symbol ?? '')
@section('content')
    <table class="meta">
        <tr>
            <td>
                <div class="label">Prepared for</div>
                <strong>{{ $quotation->customer->name }}</strong><br>
                @if($quotation->customer->company_name){{ $quotation->customer->company_name }}<br>@endif
                @if($quotation->customer->address){{ $quotation->customer->address }}<br>@endif
                @if($quotation->customer->email){{ $quotation->customer->email }}<br>@endif
                @if($quotation->customer->phone){{ $quotation->customer->phone }}@endif
            </td>
            <td class="num">
                <div class="label">Quotation number</div>
                <strong>{{ $quotation->quotation_number }}</strong><br>
                <div class="label" style="margin-top:6px">Date</div>
                {{ $quotation->quotation_date->format('M d, Y') }}<br>
                @if($quotation->expiry_date)
                    <div class="label" style="margin-top:6px">Valid until</div>
                    {{ $quotation->expiry_date->format('M d, Y') }}<br>
                @endif
                @if($quotation->reference)Ref: {{ $quotation->reference }}@endif
            </td>
        </tr>
    </table>

    @include('documents.priced-lines', ['document' => $quotation])

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $symbol }}{{ number_format((float) $quotation->subtotal, 2) }}</td></tr>
        @if((float) $quotation->discount_amount > 0)
            <tr><td>Discount</td><td class="num">−{{ $symbol }}{{ number_format((float) $quotation->discount_amount, 2) }}</td></tr>
        @endif
        <tr><td>VAT</td><td class="num">{{ $symbol }}{{ number_format((float) $quotation->tax_amount, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="num">{{ $symbol }}{{ number_format((float) $quotation->total, 2) }}</td></tr>
    </table>

    @if($quotation->notes)
        <div class="notes"><div class="label">Notes</div>{!! nl2br(e($quotation->notes)) !!}</div>
    @endif
    @if($quotation->terms)
        <div class="notes"><div class="label">Terms</div>{!! nl2br(e($quotation->terms)) !!}</div>
    @endif

    <div class="box">This is a quotation, not a bill. Prices are valid{{ $quotation->expiry_date ? ' until '.$quotation->expiry_date->format('M d, Y') : '' }}.</div>
@endsection
