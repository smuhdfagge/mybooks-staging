{{--
    The delivery note: it travels with the goods and comes back signed,
    which is the proof the customer received them. No money on it — a
    driver should not be carrying prices — just what was ordered against
    what was actually sent, and room for the signature.

    Data from App\Support\DeliveryNoteDocument::data(): $note, $tenant, $s,
    $font, $forPdf, $logo, $initials, $state, $short, $currency.
--}}
@php
    $c = $note->customer;
    $layout = $s['layout'] ?? 'classic';
    $compact = $layout === 'compact';
    $primary = $s['primary_color'];
    $accent = $s['accent_color'];
    $base = max(10, min(16, (int) $s['font_size'])) - ($compact ? 1 : 0);
    $pad = $compact ? 32 : 48;
    $lines = $note->items;
    $qty = fn ($value) => \App\Support\Figure::qty($value);
    $tenantLines = collect([
        $tenant->address,
        collect([$tenant->city, $tenant->state, $tenant->country])->filter()->implode(', '),
        $tenant->phone,
        $tenant->email,
    ])->filter();
    // Where the goods are going: the delivery address when one was given,
    // otherwise where the customer is.
    $shipTo = filled($note->shipping_address)
        ? collect(preg_split('/\r\n|\r|\n/', (string) $note->shipping_address))->filter()
        : collect([
            $c?->shipping_address ?: $c?->billing_address,
            collect([$c?->city, $c?->state, $c?->postal_code])->filter()->implode(', '),
        ])->filter();
    $sc = '.mbdoc-'.substr(md5('dn'.spl_object_id($note).json_encode($s)), 0, 8);
    $stampTone = ['delivered' => $accent, 'cancelled' => '#B42318', 'in_transit' => '#8A5A12', 'dispatched' => $primary, 'draft' => '#4A5A6B'][$state['key']] ?? null;
@endphp
<style>
    {{-- "Delivery Note" is a long title, so it sits smaller than "Invoice". --}}
    @php $titleStep = $compact ? 6 : 11; $titlePdfStep = $compact ? 4 : 8; @endphp
    @include('partials.document-styles')
    {{-- Short deliveries are called out, because that is what gets disputed. --}}
    {{ $sc }} .short { color: #B42318; font-weight: bold; }
    {{ $sc }} .note-strip { margin-top: {{ $compact ? 16 : 22 }}px; border: 1px solid {{ $s['table_border_color'] }}; page-break-inside: avoid; }
    {{ $sc }} .note-strip td { padding: {{ $compact ? '9px 12px' : '11px 14px' }}; }
</style>

<div class="mbdoc {{ ltrim($sc, '.') }}" data-delivery-note-document>
    <div class="sheet">
        <div class="head-wrap">
            <table class="head">
                <tr>
                    <td style="width: 60%;">
                        <table class="brand">
                            <tr>
                                @if ($logo)
                                    <td class="brand-mark"><img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo" data-testid="delivery-note-logo"></td>
                                @elseif ($s['show_logo'])
                                    <td class="brand-mark"><span class="mark" aria-hidden="true">{{ $initials }}</span></td>
                                @endif
                                <td>
                                    <p class="from-name">{{ $tenant->name }}</p>
                                    @foreach ($tenantLines as $line)<p class="from-line">{{ $line }}</p>@endforeach
                                    @if ($tenant->tax_number)<p class="from-line">TIN: {{ $tenant->tax_number }}</p>@endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 40%; text-align: right;">
                        <p class="title">Delivery Note</p>
                        <p class="number">{{ $note->delivery_number }}</p>
                        @if ($s['show_status_badge'])
                            <span class="stamp">{{ $state['label'] }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="parties">
            <tr>
                <td style="width: 55%; padding-right: 24px;">
                    <p class="label">Deliver to</p>
                    <p class="to-name">{{ $c?->name }}</p>
                    @if ($c?->company_name && $c->company_name !== $c->name)<p class="muted">{{ $c->company_name }}</p>@endif
                    @foreach ($shipTo as $line)<p class="muted">{{ $line }}</p>@endforeach
                    @if ($c?->phone)<p class="muted">{{ $c->phone }}</p>@endif
                </td>
                <td style="width: 45%;">
                    <table class="dates">
                        <tr><td class="k">Delivery date</td><td class="v">{{ $note->delivery_date?->format('j M Y') }}</td></tr>
                        @if ($note->invoice)<tr><td class="k">Invoice</td><td class="v">{{ $note->invoice->invoice_number }}</td></tr>@endif
                        @if ($note->shipping_method)<tr><td class="k">Sent by</td><td class="v">{{ $note->shipping_method }}</td></tr>@endif
                        @if ($note->tracking_number)<tr><td class="k">Tracking</td><td class="v">{{ $note->tracking_number }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        {{-- No prices on a delivery note: the driver should not be carrying them. --}}
        <table class="lines">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num" style="width: 18%;">Ordered</th>
                    <th class="num" style="width: 18%;">Delivered</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    @php $isShort = (float) $line->quantity_ordered > 0 && (float) $line->quantity_delivered < (float) $line->quantity_ordered; @endphp
                    <tr>
                        <td>
                            <p class="item">{{ $line->description ?: $line->item?->name }}</p>
                            @if ($line->item?->sku)<p class="sku">{{ $line->item->sku }}</p>@endif
                        </td>
                        <td class="num">{{ $qty($line->quantity_ordered) }}</td>
                        <td class="num {{ $isShort ? 'short' : '' }}">{{ $qty($line->quantity_delivered) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">No items.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($short)
            <table class="note-strip">
                <tr>
                    <td><p class="short">Some items are going out short of what was ordered. Please check the delivered column before signing.</p></td>
                </tr>
            </table>
        @endif

        @if ($s['show_notes'] && filled($note->notes))
            <table class="notes">
                <tr>
                    {{-- One column, so it takes the full width the shared styles split in two. --}}
                    <td style="width: 100%;"><p class="label">Notes</p><p class="text">{{ $note->notes }}</p></td>
                </tr>
            </table>
        @endif

        {{-- The signature is the whole point: it is the proof of delivery. --}}
        <table class="signs">
            <tr>
                <td>
                    <div class="rule"></div>
                    <p class="role">Dispatched by</p>
                    <p class="who">{{ $tenant->name }}</p>
                </td>
                <td>
                    <div class="rule"></div>
                    <p class="role">Delivered by</p>
                    <p class="who">Driver's name and signature</p>
                </td>
                <td class="last">
                    <div class="rule"></div>
                    <p class="role">Received by</p>
                    <p class="who">
                        @if ($note->received_by)
                            {{ $note->received_by }}@if ($note->received_at), {{ $note->received_at->format('j M Y') }}@endif
                        @else
                            Name, signature and date
                        @endif
                    </p>
                </td>
            </tr>
        </table>

        @if ($s['show_footer'])
            <div class="foot">
                <p>Goods received in good order unless noted above.</p>
                <p>
                    @foreach (collect([$tenant->name, $tenant->phone, $tenant->email])->filter()->values() as $bit)
                        @if (! $loop->first)<span class="sep">|</span>@endif<span>{{ $bit }}</span>
                    @endforeach
                </p>
            </div>
        @endif
    </div>
</div>
