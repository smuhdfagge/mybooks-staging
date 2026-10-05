@extends('documents.layout', ['title' => 'DELIVERY NOTE', 'number' => $deliveryNote->delivery_number])

@section('content')
    <table class="meta">
        <tr>
            <td>
                <div class="label">Deliver to</div>
                <strong>{{ $deliveryNote->customer->name }}</strong><br>
                @if($deliveryNote->customer->company_name){{ $deliveryNote->customer->company_name }}<br>@endif
                {!! nl2br(e($deliveryNote->shipping_address ?: $deliveryNote->customer->address)) !!}<br>
                @if($deliveryNote->customer->phone){{ $deliveryNote->customer->phone }}@endif
            </td>
            <td class="num">
                <div class="label">Delivery note number</div>
                <strong>{{ $deliveryNote->delivery_number }}</strong><br>
                <div class="label" style="margin-top:6px">Date</div>
                {{ $deliveryNote->delivery_date->format('M d, Y') }}<br>
                @if($deliveryNote->salesOrder)
                    <div class="label" style="margin-top:6px">Sales order</div>
                    {{ $deliveryNote->salesOrder->order_number }}<br>
                @endif
                @if($deliveryNote->shipping_method)Delivered by: {{ $deliveryNote->shipping_method }}<br>@endif
                @if($deliveryNote->tracking_number)Vehicle / tracking: {{ $deliveryNote->tracking_number }}@endif
                @if($warehouseName = \App\Models\Warehouse::nameIfMany($deliveryNote->warehouse_id))<br>Sent from: {{ $warehouseName }}@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Description</th><th class="num">Ordered</th><th class="num">Delivered</th></tr>
        </thead>
        <tbody>
            @foreach($deliveryNote->items as $line)
                <tr>
                    <td>{{ $line->description ?: $line->item?->name }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity_ordered, 2), '0'), '.') }}</td>
                    <td class="num"><strong>{{ rtrim(rtrim(number_format((float) $line->quantity_delivered, 2), '0'), '.') }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($deliveryNote->notes)
        <div class="notes"><div class="label">Notes</div>{!! nl2br(e($deliveryNote->notes)) !!}</div>
    @endif

    <table class="signatures">
        <tr>
            <td><div class="sign-line">Delivered by (name, signature, date)</div></td>
            <td><div class="sign-line">Received in good condition by (name, signature, date){{ $deliveryNote->received_by ? ': '.$deliveryNote->received_by : '' }}</div></td>
        </tr>
    </table>
@endsection
