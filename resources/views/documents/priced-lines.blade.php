{{-- Line table for priced documents (quotation, credit note). --}}
<table class="lines">
    <thead>
        <tr>
            <th>Description</th>
            <th class="num">Qty</th>
            <th class="num">Unit price</th>
            @if($document->items->contains(fn ($l) => (float) ($l->discount ?? 0) > 0))
                <th class="num">Discount</th>
            @endif
            <th class="num">VAT</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @php($hasDiscount = $document->items->contains(fn ($l) => (float) ($l->discount ?? 0) > 0))
        @foreach($document->items as $line)
            <tr>
                <td>{{ $line->description ?: $line->item?->name }}</td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                <td class="num">{{ $symbol }}{{ number_format((float) $line->unit_price, 2) }}</td>
                @if($hasDiscount)
                    <td class="num">{{ (float) $line->discount > 0 ? $symbol.number_format((float) $line->discount, 2) : '—' }}</td>
                @endif
                <td class="num">{{ (float) $line->tax_rate > 0 ? rtrim(rtrim(number_format((float) $line->tax_rate, 2), '0'), '.').'%' : '—' }}</td>
                <td class="num">{{ $symbol }}{{ number_format((float) $line->total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
