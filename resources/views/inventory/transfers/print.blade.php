@extends('documents.layout', ['title' => 'STOCK TRANSFER NOTE', 'number' => $transfer->transfer_number])

@section('content')
    <table class="meta">
        <tr>
            <td>
                <div class="label">From</div>
                <strong>{{ $transfer->fromWarehouse?->name }}</strong><br>
                @if($transfer->fromWarehouse?->address){!! nl2br(e($transfer->fromWarehouse->address)) !!}<br>@endif
                @if($transfer->fromWarehouse?->phone){{ $transfer->fromWarehouse->phone }}<br>@endif
                <div class="label" style="margin-top:8px">To</div>
                <strong>{{ $transfer->toWarehouse?->name }}</strong><br>
                @if($transfer->toWarehouse?->address){!! nl2br(e($transfer->toWarehouse->address)) !!}<br>@endif
                @if($transfer->toWarehouse?->contact_person || $transfer->toWarehouse?->phone){{ trim($transfer->toWarehouse->contact_person.' '.$transfer->toWarehouse->phone) }}@endif
            </td>
            <td class="num">
                <div class="label">Transfer number</div>
                <strong>{{ $transfer->transfer_number }}</strong><br>
                <div class="label" style="margin-top:6px">Date sent</div>
                {{ $transfer->transfer_date?->format('M d, Y') }}<br>
                @if($transfer->reference)
                    <div class="label" style="margin-top:6px">Reference</div>
                    {{ $transfer->reference }}<br>
                @endif
                @if($transfer->received_date)
                    <div class="label" style="margin-top:6px">Date received</div>
                    {{ $transfer->received_date->format('M d, Y') }}
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Item</th><th>SKU</th><th style="text-align:right">Quantity sent</th><th style="text-align:right">Quantity received</th></tr>
        </thead>
        <tbody>
            @foreach($transfer->items as $line)
                <tr>
                    <td>{{ $line->item?->name }}</td>
                    <td>{{ $line->item?->sku }}</td>
                    <td class="num"><strong>{{ rtrim(rtrim(number_format((float) $line->quantity, 4), '0'), '.') }}</strong></td>
                    <td class="num">@if($transfer->status === 'received'){{ rtrim(rtrim(number_format((float) $line->quantity_received, 4), '0'), '.') }}@else&nbsp;@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($transfer->notes)
        <div class="notes"><div class="label">Notes</div>{!! nl2br(e($transfer->notes)) !!}</div>
    @endif

    <table class="signatures">
        <tr>
            <td><div class="sign-line">Sent by (name, signature, date)</div></td>
            <td><div class="sign-line">Received in good condition by (name, signature, date)</div></td>
        </tr>
    </table>
@endsection
