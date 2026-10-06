@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $bom = $order->billOfMaterial;
    $finished = $bom?->item;
    $unit = $finished?->unit ? ' '.$finished->unit : '';
    $done = $order->isCompleted();
    $breakdown = $order->isBreakdown();
    $planned = $order->plannedQuantity();
    $fromName = \App\Models\Warehouse::nameIfMany($order->warehouse_id);
    $toName = \App\Models\Warehouse::nameIfMany($order->to_warehouse_id);
@endphp
@extends('documents.layout', ['title' => $breakdown ? 'BREAK-DOWN SHEET' : 'PRODUCTION SHEET', 'number' => $order->order_number])

@section('content')
    <style>@page { size: A4; margin: 12mm; }</style>
    <table class="meta">
        <tr>
            <td>
                <div class="label">{{ $breakdown ? 'Breaking down' : 'Making' }}</div>
                <strong style="font-size:14px">{{ $finished?->name }}</strong><br>
                @if($finished?->sku)<span class="muted">{{ $finished->sku }}</span><br>@endif
                <span class="muted">{{ $bom?->label() }}@if($bom) · one batch makes {{ $fmt($bom->output_quantity) }}{{ $unit }}@endif</span>
                @if($fromName)
                    <div class="label" style="margin-top:8px">{{ $breakdown ? 'Items from' : 'Components from' }}</div>{{ $fromName }}
                    <div class="label" style="margin-top:6px">{{ $breakdown ? 'Parts to' : 'Finished goods to' }}</div>{{ $toName }}
                @endif
            </td>
            <td class="num">
                <div class="label">Order number</div>
                <strong>{{ $order->order_number }}</strong><br>
                <div class="label" style="margin-top:6px">Date</div>
                {{ $order->assembly_date?->format('M d, Y') }}<br>
                <div class="label" style="margin-top:6px">Planned</div>
                <strong>{{ $fmt($planned) }}{{ $unit }}</strong><br>
                <div class="label" style="margin-top:6px">{{ $breakdown ? 'Broken down' : 'Actually made' }}</div>
                @if($done)<strong>{{ $fmt($order->quantity_made) }}{{ $unit }}</strong>@else ____________ @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ $breakdown ? 'Part' : 'Component' }}</th>
                <th style="text-align:right">Planned</th>
                <th style="text-align:right">{{ $breakdown ? 'Got back' : 'Actually used' }}</th>
                @if($done)<th style="text-align:right">Cost</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $line)
                @php $lineUnit = $line->item?->unit ? ' '.$line->item->unit : ''; @endphp
                <tr>
                    <td>{{ $line->item?->name }}@if($line->item?->sku) <span class="muted">({{ $line->item->sku }})</span>@endif</td>
                    <td class="num"><strong>{{ $fmt($line->planned_quantity) }}{{ $lineUnit }}</strong></td>
                    <td class="num">@if($done){{ $fmt($line->quantity) }}{{ $lineUnit }}@else&nbsp;@endif</td>
                    @if($done)<td class="num">@money($line->cost)</td>@endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($order->costs->isNotEmpty())
        <table class="lines">
            <thead><tr><th>Extra cost</th><th style="text-align:right">Planned</th><th style="text-align:right">Actual</th></tr></thead>
            <tbody>
                @foreach($order->costs as $cost)
                    <tr>
                        <td>{{ $cost->description }}</td>
                        <td class="num">@money($cost->planned_amount)</td>
                        <td class="num">@if($done)@money($cost->amount)@else&nbsp;@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($done)
        <table class="totals">
            <tr><td>{{ $breakdown ? 'Cost of items broken down' : 'Components' }}</td><td class="num">@money($order->components_cost)</td></tr>
            @unless($breakdown)<tr><td>Extra costs</td><td class="num">@money($order->extra_cost)</td></tr>@endunless
            <tr class="grand"><td>Total cost</td><td class="num">@money($order->total_cost)</td></tr>
            <tr><td>Each ({{ $fmt($order->quantity_made) }}{{ $unit }})</td><td class="num">@money($order->unit_cost)</td></tr>
        </table>
    @endif

    @if($order->notes)
        <div class="notes"><div class="label">Notes</div>{!! nl2br(e($order->notes)) !!}</div>
    @endif

    <table class="signatures">
        <tr>
            <td><div class="sign-line">{{ $breakdown ? 'Broken down by' : 'Made by' }} (name, signature, date)</div></td>
            <td><div class="sign-line">Checked by (name, signature, date)</div></td>
        </tr>
    </table>
@endsection
