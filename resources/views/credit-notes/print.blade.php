@extends('documents.layout', ['title' => 'CREDIT NOTE', 'number' => $creditNote->credit_note_number])

@php($symbol = $tenant->currency_symbol ?? '')
@section('content')
    <table class="meta">
        <tr>
            <td>
                <div class="label">Credit to</div>
                <strong>{{ $creditNote->customer->name }}</strong><br>
                @if($creditNote->customer->company_name){{ $creditNote->customer->company_name }}<br>@endif
                @if($creditNote->customer->address){{ $creditNote->customer->address }}<br>@endif
                @if($creditNote->customer->email){{ $creditNote->customer->email }}@endif
            </td>
            <td class="num">
                <div class="label">Credit note number</div>
                <strong>{{ $creditNote->credit_note_number }}</strong><br>
                <div class="label" style="margin-top:6px">Date</div>
                {{ $creditNote->credit_note_date->format('M d, Y') }}<br>
                @if($creditNote->invoice)
                    <div class="label" style="margin-top:6px">Against invoice</div>
                    {{ $creditNote->invoice->invoice_number }}<br>
                @endif
                @if($creditNote->reason)Reason: {{ \App\Models\CreditNote::REASONS[$creditNote->reason] ?? $creditNote->reason }}@endif
            </td>
        </tr>
    </table>

    @include('documents.priced-lines', ['document' => $creditNote])

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $symbol }}{{ number_format((float) $creditNote->subtotal, 2) }}</td></tr>
        <tr><td>VAT</td><td class="num">{{ $symbol }}{{ number_format((float) $creditNote->tax_amount, 2) }}</td></tr>
        <tr class="grand"><td>Total credit</td><td class="num">{{ $symbol }}{{ number_format((float) $creditNote->total, 2) }}</td></tr>
    </table>

    @if($creditNote->notes)
        <div class="notes"><div class="label">Notes</div>{!! nl2br(e($creditNote->notes)) !!}</div>
    @endif
    @if($creditNote->restock)
        <div class="box">The goods listed were returned by the customer.</div>
    @endif
@endsection
