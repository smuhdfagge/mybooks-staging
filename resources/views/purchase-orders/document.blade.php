{{--
    The purchase order: the business's written instruction to a supplier
    to deliver and to invoice. Most suppliers will not move without it.
    The same markup is shown on the order page, printed from it, and
    turned into the PDF.

    Data from App\Support\PurchaseOrderDocument::data(): $order, $tenant,
    $s, $font, $forPdf, $logo, $initials, $state, $showReceived, $inWords,
    $currency.
--}}
@php
    $v = $order->vendor;
    $sym = \App\Support\Money::symbol($currency);
    $money = fn ($value) => \App\Support\Money::format($value, $currency);
    $num = fn ($value) => number_format((float) $value, 2);
    $layout = $s['layout'] ?? 'classic';
    $compact = $layout === 'compact';
    $primary = $s['primary_color'];
    $accent = $s['accent_color'];
    $base = max(10, min(16, (int) $s['font_size'])) - ($compact ? 1 : 0);
    $pad = $compact ? 32 : 48;
    $showTax = (bool) $s['show_tax_column'];
    $hasDiscount = (float) $order->discount_amount > 0;
    $lines = $order->items;
    $tenantLines = collect([
        $tenant->address,
        collect([$tenant->city, $tenant->state, $tenant->country])->filter()->implode(', '),
        $tenant->phone,
        $tenant->email,
    ])->filter();
    $vendorLines = collect([
        $v?->company_name && $v->company_name !== $v->name ? $v->company_name : null,
        $v?->address,
        collect([$v?->city, $v?->state, $v?->postal_code])->filter()->implode(', '),
        $v?->email,
        $v?->phone,
    ])->filter();
    // The supplier needs to know where to send the goods.
    $deliverTo = collect([
        $tenant->address,
        collect([$tenant->city, $tenant->state, $tenant->country])->filter()->implode(', '),
    ])->filter();
    $sc = '.mbdoc-'.substr(md5('po'.spl_object_id($order).json_encode($s)), 0, 8);
    $stampTone = ['billed' => $accent, 'received' => $accent, 'cancelled' => '#B42318', 'partially_received' => '#8A5A12', 'draft' => '#4A5A6B', 'confirmed' => $primary][$state['key']] ?? null;
@endphp
<style>
    {{-- "Purchase Order" is a long title, so it sits smaller than "Invoice". --}}
    @php $titleStep = $compact ? 5 : 10; $titlePdfStep = $compact ? 4 : 7; @endphp
    @include('partials.document-styles')
</style>

<div class="mbdoc {{ ltrim($sc, '.') }}" data-purchase-order-document>
    <div class="sheet">
        <div class="head-wrap">
            <table class="head">
                <tr>
                    <td style="width: 58%;">
                        <table class="brand">
                            <tr>
                                @if ($logo)
                                    <td class="brand-mark"><img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo" data-testid="purchase-order-logo"></td>
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
                    <td style="width: 42%; text-align: right;">
                        <p class="title">Purchase Order</p>
                        <p class="number">{{ $order->order_number }}</p>
                        @if ($s['show_status_badge'] && $state['key'] !== 'confirmed')
                            <span class="stamp">{{ $state['label'] }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="parties">
            <tr>
                <td style="width: 55%; padding-right: 24px;">
                    <p class="label">Order to</p>
                    <p class="to-name">{{ $v?->name ?? 'Not recorded' }}</p>
                    @foreach ($vendorLines as $line)<p class="muted">{{ $line }}</p>@endforeach
                    @if ($v?->tax_number)<p class="muted">TIN: {{ $v->tax_number }}</p>@endif
                </td>
                <td style="width: 45%;">
                    <table class="dates">
                        <tr><td class="k">Order date</td><td class="v">{{ $order->order_date?->format('j M Y') }}</td></tr>
                        @if ($order->expected_date)<tr><td class="k">Wanted by</td><td class="v">{{ $order->expected_date->format('j M Y') }}</td></tr>@endif
                        @if ($order->reference)<tr><td class="k">Reference</td><td class="v">{{ $order->reference }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        {{-- Where to send the goods: the supplier cannot deliver without it. --}}
        @if ($deliverTo->isNotEmpty())
            <table class="parties" style="margin-top: 14px;">
                <tr>
                    <td style="width: 100%;">
                        <p class="label">Deliver to</p>
                        <p class="muted" style="color: #111827;">{{ $tenant->name }}</p>
                        @foreach ($deliverTo as $line)<p class="muted">{{ $line }}</p>@endforeach
                    </td>
                </tr>
            </table>
        @endif

        <table class="due">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">Order value</p>
                    <p class="amount">{{ $money($order->total) }}</p>
                </td>
                <td class="when">
                    @if ($state['key'] === 'cancelled')
                        This order was cancelled.
                    @elseif ($state['key'] === 'draft')
                        Not sent to the supplier yet.
                    @elseif ($order->expected_date)
                        Wanted by <strong>{{ $order->expected_date->format('j M Y') }}</strong>
                    @else
                        Please confirm receipt of this order.
                    @endif
                </td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="num" style="width: 11%;">Qty</th>
                    @if ($showReceived)<th class="num" style="width: 13%;">Received</th>@endif
                    <th class="num" style="width: 16%;">Price</th>
                    @if ($showTax)<th class="num col-tax" style="width: 9%;">VAT</th>@endif
                    <th class="num" style="width: 17%;">Amount ({{ $sym }})</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    @php $isShort = $showReceived && (float) $line->quantity_received < (float) $line->quantity; @endphp
                    <tr>
                        <td>
                            <p class="item">{{ $line->description ?: $line->item?->name }}</p>
                            @if ($line->item?->sku)<p class="sku">{{ $line->item->sku }}</p>@endif
                        </td>
                        <td class="num">{{ \App\Support\Figure::qty($line->quantity) }}</td>
                        @if ($showReceived)<td class="num {{ $isShort ? 'short' : '' }}">{{ \App\Support\Figure::qty($line->quantity_received) }}</td>@endif
                        <td class="num">{{ $num($line->unit_price) }}</td>
                        @if ($showTax)<td class="num col-tax">{{ (float) $line->tax_rate > 0 ? \App\Support\Figure::rate($line->tax_rate) : '—' }}</td>@endif
                        {{-- Before VAT, so the lines add up to the subtotal. --}}
                        <td class="num">{{ $num((float) $line->total - (float) $line->tax_amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 3 + ($showReceived ? 1 : 0) + ($showTax ? 1 : 0) }}" class="muted">No items.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr><td class="muted">Subtotal</td><td class="num">{{ $num($order->subtotal) }}</td></tr>
            @if ($hasDiscount)<tr><td class="muted">Discount</td><td class="num">-{{ $num($order->discount_amount) }}</td></tr>@endif
            <tr><td class="muted">VAT</td><td class="num">{{ $num($order->tax_amount) }}</td></tr>
            <tr class="grand"><td>Order total</td><td class="num">{{ $money($order->total) }}</td></tr>
        </table>

        <table class="words">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">Amount in words</p>
                    <p class="text">{{ $inWords }}</p>
                </td>
            </tr>
        </table>

        @php
            $showNotes = $s['show_notes'] && filled($order->notes);
            $showTerms = $s['show_terms'] && filled($order->terms);
        @endphp
        @if ($showNotes || $showTerms)
            <table class="notes">
                <tr>
                    @if ($showNotes)<td @if (! $showTerms) style="width: 100%;" @endif><p class="label">Notes</p><p class="text">{{ $order->notes }}</p></td>@endif
                    @if ($showTerms)<td @if (! $showNotes) style="width: 100%;" @endif><p class="label">Terms</p><p class="text">{{ $order->terms }}</p></td>@endif
                </tr>
            </table>
        @endif

        {{-- A purchase order is an authorisation to spend, so it is signed. --}}
        <table class="signs">
            <tr>
                <td>
                    <div class="rule"></div>
                    <p class="role">Raised by</p>
                    <p class="who">{{ $order->createdBy?->name ?? 'Name, signature and date' }}</p>
                </td>
                <td class="last" style="width: 66.66%; padding-left: 18px; padding-right: 0;">
                    <div class="rule"></div>
                    <p class="role">Authorised for {{ $tenant->name }}</p>
                    <p class="who">Name, signature and date</p>
                </td>
            </tr>
        </table>

        @if ($s['show_footer'])
            {{-- No invoice footer line here: that one thanks a customer, and
                 a purchase order goes to a supplier. --}}
            <div class="foot">
                <p>Please quote order {{ $order->order_number }} on your invoice and delivery note.</p>
                <p>
                    @foreach (collect([$tenant->name, $tenant->phone, $tenant->email, $tenant->website ? preg_replace('#^https?://#', '', $tenant->website) : null])->filter()->values() as $bit)
                        @if (! $loop->first)<span class="sep">|</span>@endif<span>{{ $bit }}</span>
                    @endforeach
                </p>
            </div>
        @endif
    </div>
</div>
