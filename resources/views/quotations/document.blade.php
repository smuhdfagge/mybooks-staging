{{--
    The quotation: the first paper a customer sees of this business, and
    the one that wins the work. The same markup is shown on the quotation
    page, printed from it, and turned into the PDF. When the customer says
    yes it becomes the invoice, which is drawn from the same template, so
    the two match.

    Data from App\Support\QuotationDocument::data(): $quotation, $tenant,
    $s, $font, $forPdf, $logo, $initials, $state, $currency.
--}}
@php
    $c = $quotation->customer;
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
    $hasDiscount = (float) $quotation->discount_amount > 0;
    $lines = $quotation->items;
    $tenantLines = collect([
        $tenant->address,
        collect([$tenant->city, $tenant->state, $tenant->country])->filter()->implode(', '),
        $tenant->phone,
        $tenant->email,
    ])->filter();
    $customerLines = collect([
        $c?->company_name && $c->company_name !== $c->name ? $c->company_name : null,
        $c?->billing_address,
        collect([$c?->city, $c?->state, $c?->postal_code])->filter()->implode(', '),
        $c?->email,
        $c?->phone,
    ])->filter();
    $sc = '.mbdoc-'.substr(md5('quo'.spl_object_id($quotation).json_encode($s)), 0, 8);
    $stampTone = ['accepted' => $accent, 'converted' => $accent, 'rejected' => '#B42318', 'expired' => '#B42318', 'draft' => '#4A5A6B'][$state['key']] ?? null;
@endphp
<style>
    {{-- "Quotation" is longer than "Invoice", so it sits a little smaller. --}}
    @php $titleStep = $compact ? 7 : 12; $titlePdfStep = $compact ? 5 : 9; @endphp
    @include('partials.document-styles')
</style>

<div class="mbdoc {{ ltrim($sc, '.') }}" data-quotation-document>
    <div class="sheet">
        <div class="head-wrap">
            <table class="head">
                <tr>
                    <td style="width: 62%;">
                        <table class="brand">
                            <tr>
                                @if ($logo)
                                    <td class="brand-mark"><img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo" data-testid="quotation-logo"></td>
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
                    <td style="width: 38%; text-align: right;">
                        <p class="title">Quotation</p>
                        <p class="number">{{ $quotation->quotation_number }}</p>
                        @if ($s['show_status_badge'] && $state['key'] !== 'sent')
                            <span class="stamp">{{ $state['label'] }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="parties">
            <tr>
                <td style="width: 55%; padding-right: 24px;">
                    <p class="label">Prepared for</p>
                    <p class="to-name">{{ $c?->name }}</p>
                    @foreach ($customerLines as $line)<p class="muted">{{ $line }}</p>@endforeach
                    @if ($c?->tax_number)<p class="muted">TIN: {{ $c->tax_number }}</p>@endif
                </td>
                <td style="width: 45%;">
                    <table class="dates">
                        <tr><td class="k">Date</td><td class="v">{{ $quotation->quotation_date?->format('j M Y') }}</td></tr>
                        @if ($quotation->expiry_date)<tr><td class="k">Valid until</td><td class="v">{{ $quotation->expiry_date->format('j M Y') }}</td></tr>@endif
                        @if ($quotation->reference)<tr><td class="k">Reference</td><td class="v">{{ $quotation->reference }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="due">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">Quoted price</p>
                    <p class="amount">{{ $money($quotation->total) }}</p>
                </td>
                <td class="when">
                    @if ($state['key'] === 'converted')
                        Accepted and invoiced.
                    @elseif ($state['key'] === 'accepted')
                        Accepted, thank you.
                    @elseif ($state['key'] === 'rejected')
                        This quotation was declined.
                    @elseif ($state['expired'])
                        This price expired on <strong>{{ $quotation->expiry_date?->format('j M Y') }}</strong>
                    @elseif ($quotation->expiry_date)
                        Held until <strong>{{ $quotation->expiry_date->format('j M Y') }}</strong>
                    @else
                        Prices are subject to change.
                    @endif
                </td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="num" style="width: 12%;">Qty</th>
                    <th class="num" style="width: 17%;">Price</th>
                    @if ($showTax)<th class="num col-tax" style="width: 10%;">VAT</th>@endif
                    <th class="num" style="width: 18%;">Amount ({{ $sym }})</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td>
                            <p class="item">{{ $line->description }}</p>
                            @if ($line->item?->sku)<p class="sku">{{ $line->item->sku }}</p>@endif
                        </td>
                        <td class="num">{{ \App\Support\Figure::qty($line->quantity) }}</td>
                        <td class="num">{{ $num($line->unit_price) }}</td>
                        @if ($showTax)<td class="num col-tax">{{ (float) $line->tax_rate > 0 ? \App\Support\Figure::rate($line->tax_rate) : '—' }}</td>@endif
                        {{-- Before VAT, so the lines add up to the subtotal. --}}
                        <td class="num">{{ $num((float) $line->total - (float) $line->tax_amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showTax ? 5 : 4 }}" class="muted">No items.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr><td class="muted">Subtotal</td><td class="num">{{ $num($quotation->subtotal) }}</td></tr>
            @if ($hasDiscount)<tr><td class="muted">Discount</td><td class="num">-{{ $num($quotation->discount_amount) }}</td></tr>@endif
            <tr><td class="muted">VAT</td><td class="num">{{ $num($quotation->tax_amount) }}</td></tr>
            <tr class="grand"><td>Total</td><td class="num">{{ $money($quotation->total) }}</td></tr>
        </table>

        @php
            $showNotes = $s['show_notes'] && filled($quotation->notes);
            $showTerms = $s['show_terms'] && filled($quotation->terms);
        @endphp
        @if ($showNotes || $showTerms)
            <table class="notes">
                <tr>
                    @if ($showNotes)<td><p class="label">Notes</p><p class="text">{{ $quotation->notes }}</p></td>@endif
                    @if ($showTerms)<td><p class="label">Terms</p><p class="text">{{ $quotation->terms }}</p></td>@endif
                </tr>
            </table>
        @endif

        {{-- A signed quotation is the customer's go-ahead. --}}
        <table class="signs">
            <tr>
                <td>
                    <div class="rule"></div>
                    <p class="role">Prepared by</p>
                    <p class="who">{{ $tenant->name }}</p>
                </td>
                <td class="last" style="width: 66.66%; padding-left: 18px; padding-right: 0;">
                    <div class="rule"></div>
                    <p class="role">Accepted for {{ $c?->name ?: 'the customer' }}</p>
                    <p class="who">Name, signature and date</p>
                </td>
            </tr>
        </table>

        @if ($s['show_footer'])
            <div class="foot">
                @if (filled($s['footer_text']))<p class="thanks">{{ $s['footer_text'] }}</p>@endif
                <p>
                    @foreach (collect([$tenant->name, $tenant->phone, $tenant->email, $tenant->website ? preg_replace('#^https?://#', '', $tenant->website) : null])->filter()->values() as $bit)
                        @if (! $loop->first)<span class="sep">|</span>@endif<span>{{ $bit }}</span>
                    @endforeach
                </p>
            </div>
        @endif
    </div>
</div>
