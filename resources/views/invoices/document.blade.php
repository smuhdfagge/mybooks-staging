{{--
    The invoice itself: the one document the customer receives (invoice
    rebuild). The same markup is shown on the invoice page, printed from it,
    turned into the PDF, and previewed in Settings > Invoice templates.

    Data from App\Support\InvoiceDocument::data(): $invoice, $tenant, $s
    (template settings), $font, $forPdf, $logo, $initials, $state, $paid, $credited,
    $currency.

    Plain tables and class selectors only, scoped to .mbdoc, so DomPDF draws
    the same page as the browser (no flex, grid or CSS variables).
--}}
@php
    $c = $invoice->customer;
    $sym = \App\Support\Money::symbol($currency);
    $money = fn ($v) => \App\Support\Money::format($v, $currency);
    $num = fn ($v) => number_format((float) $v, 2);
    $layout = $s['layout'] ?? 'classic';
    $compact = $layout === 'compact';
    $primary = $s['primary_color'];
    $accent = $s['accent_color'];
    $base = max(10, min(16, (int) $s['font_size'])) - ($compact ? 1 : 0);
    $pad = $compact ? 32 : 48;
    $showTax = (bool) $s['show_tax_column'];
    $hasDiscount = (float) $invoice->discount_amount > 0;
    $balance = round((float) $invoice->balance_due, 2);
    $lines = $invoice->items;
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
    // Each document styles only itself, so several can share a page (template previews).
    $sc = '.mbdoc-'.substr(md5(spl_object_id($invoice).json_encode($s)), 0, 8);
    $stampTone = ['paid' => $accent, 'overdue' => '#B42318', 'partial' => '#8A5A12', 'draft' => '#4A5A6B', 'cancelled' => '#4A5A6B'][$state['key']] ?? null;
@endphp
<style>
    {{ $sc }} { font-family: {!! $font !!}; font-size: {{ $base }}px; line-height: 1.5; color: #1F2937; background: #fff; }
    {{ $sc }} * { box-sizing: border-box; }
    {{ $sc }} table { border-collapse: collapse; width: 100%; }
    {{ $sc }} td, {{ $sc }} th { padding: 0; vertical-align: top; }
    {{ $sc }} p { margin: 0; }
    {{ $sc }} .sheet { padding: {{ $pad }}px {{ $pad }}px {{ $compact ? 28 : 36 }}px; }
    {{ $sc }} .muted { color: #5B6B7B; }
    {{ $sc }} .num { text-align: right; white-space: nowrap; }

    /* Head: who it is from, and what it is */
    {{ $sc }} .head td { vertical-align: top; }
    {{ $sc }} .brand { width: auto; }
    {{ $sc }} .brand-mark { padding-right: 14px; vertical-align: top; }
    {{ $sc }} .logo { display: block; max-height: {{ $compact ? 44 : 64 }}px; max-width: 170px; }
    {{ $sc }} .mark { display: inline-block; width: {{ $compact ? 40 : 52 }}px; height: {{ $compact ? 40 : 52 }}px; line-height: {{ $compact ? 40 : 52 }}px; border-radius: 10px; background: {{ $primary }}; color: #fff; text-align: center; font-weight: bold; font-size: {{ $compact ? $base + 2 : $base + 6 }}px; letter-spacing: 0.5px; }
    {{ $sc }} .from-name { font-size: {{ $base + 4 }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#111827' }}; }
    {{ $sc }} .from-line { font-size: {{ $base - 1 }}px; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#5B6B7B' }}; }
    {{ $sc }} .title { font-size: {{ $compact ? $base + 8 : $base + 14 }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : $primary }}; text-align: right; line-height: 1.1; letter-spacing: -0.2px; }
    {{ $sc }} .number { font-size: {{ $base + 1 }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#111827' }}; text-align: right; margin-top: 6px; }
    {{ $sc }} .stamp { display: inline-block; margin-top: 8px; padding: 2px 10px; border: 1.5px solid {{ $stampTone ?? '#4A5A6B' }}; color: {{ $stampTone ?? '#4A5A6B' }}; border-radius: 4px; font-size: {{ $base - 2 }}px; font-weight: bold; }
    @if ($layout === 'modern')
        {{ $sc }} .head-wrap { background: {{ $s['header_bg_color'] }}; margin: -{{ $pad }}px -{{ $pad }}px 0; padding: {{ $pad }}px {{ $pad }}px {{ $compact ? 20 : 28 }}px; border-bottom: {{ max(1, (int) $s['border_width']) }}px {{ $s['border_style'] }} {{ $primary }}; }
    @elseif ($layout === 'minimal')
        {{ $sc }} .head-wrap { padding-bottom: {{ $compact ? 16 : 24 }}px; border-bottom: 1px solid #E5E7EB; }
    @else
        {{ $sc }} .head-wrap { padding-bottom: {{ $compact ? 16 : 24 }}px; border-bottom: {{ max(1, (int) $s['border_width']) }}px {{ $s['border_style'] }} {{ $primary }}; }
    @endif

    /* Parties and dates */
    {{ $sc }} .parties { margin-top: {{ $compact ? 18 : 28 }}px; }
    {{ $sc }} .label { font-size: {{ $base - 2 }}px; color: #5B6B7B; padding-bottom: 4px; }
    {{ $sc }} .to-name { font-size: {{ $base + 2 }}px; font-weight: bold; color: #111827; }
    {{ $sc }} .dates td { padding: 2px 0; }
    {{ $sc }} .dates .k { color: #5B6B7B; padding-right: 18px; }
    {{ $sc }} .dates .v { text-align: right; font-weight: bold; color: #111827; white-space: nowrap; }

    /* What is owed, at a glance */
    {{ $sc }} .due { margin-top: {{ $compact ? 18 : 26 }}px; background: #F3F6F9; border-left: 4px solid {{ $primary }}; }
    {{ $sc }} .due td { padding: {{ $compact ? '10px 14px' : '14px 18px' }}; vertical-align: middle; }
    {{ $sc }} .due .amount { font-size: {{ $base + 9 }}px; font-weight: bold; color: #111827; }
    {{ $sc }} .due .when { text-align: right; color: #374151; }

    /* Lines */
    {{ $sc }} .lines { margin-top: {{ $compact ? 18 : 26 }}px; }
    {{ $sc }} .lines th { background: {{ $s['table_header_bg'] }}; color: {{ $s['table_header_text'] }}; font-size: {{ $base - 2 }}px; font-weight: bold; text-align: left; padding: 8px 10px; border-bottom: 1px solid {{ $s['table_border_color'] }}; }
    {{ $sc }} .lines th.num { text-align: right; }
    {{ $sc }} .lines td { padding: {{ $compact ? '7px 10px' : '10px 10px' }}; border-bottom: 1px solid {{ $s['table_border_color'] }}; }
    {{ $sc }} .lines tr { page-break-inside: avoid; }
    {{ $sc }} .lines thead { display: table-header-group; }
    {{ $sc }} .item { font-weight: bold; color: #111827; }
    {{ $sc }} .sku { font-size: {{ $base - 2 }}px; color: #5B6B7B; }

    /* Totals, closed with the accountant's double rule */
    {{ $sc }} .totals { width: 46%; margin-left: 54%; margin-top: 14px; page-break-inside: avoid; }
    {{ $sc }} .totals td { padding: 4px 10px; }
    {{ $sc }} .totals .grand td { padding-top: 10px; padding-bottom: 8px; font-size: {{ $base + 3 }}px; font-weight: bold; color: #111827; border-top: 1px solid #D1D5DB; }
    {{ $sc }} .totals .grand td.num { border-bottom: 3px double {{ $primary }}; }
    {{ $sc }} .totals .paid td { color: {{ $accent }}; }
    {{ $sc }} .totals .balance td { font-weight: bold; color: #111827; background: #F3F6F9; padding-top: 8px; padding-bottom: 8px; }

    /* Notes, terms, footer */
    {{ $sc }} .notes { margin-top: {{ $compact ? 22 : 32 }}px; page-break-inside: avoid; }
    {{ $sc }} .notes td { width: 50%; padding-right: 18px; }
    {{ $sc }} .notes .text { white-space: pre-line; color: #374151; font-size: {{ $base - 1 }}px; }
    {{ $sc }} .foot { margin-top: {{ $compact ? 24 : 36 }}px; padding-top: 14px; border-top: 1px solid #E5E7EB; text-align: center; color: {{ $s['footer_text_color'] }}; font-size: {{ $base - 1 }}px; page-break-inside: avoid; }
    {{ $sc }} .foot .sep { padding: 0 8px; color: #CBD5E1; }
    {{ $sc }} .foot .thanks { color: {{ $primary }}; font-weight: bold; font-size: {{ $base }}px; margin-bottom: 2px; }

    @unless ($forPdf)
    /* A phone: the same invoice, reflowed to fit (screen only). */
    @media screen and (max-width: 640px) {
        {{ $sc }} .sheet { padding: 22px 16px 24px; }
        {{ $sc }} .head-wrap { margin-left: {{ $layout === 'modern' ? '-16px' : '0' }} !important; margin-right: {{ $layout === 'modern' ? '-16px' : '0' }} !important; margin-top: {{ $layout === 'modern' ? '-22px' : '0' }} !important; }
        {{ $sc }} .title { font-size: {{ $base + 8 }}px; }
        {{ $sc }} .logo { max-width: 84px; max-height: 44px; }
        {{ $sc }} .brand-mark { padding-right: 10px; }
        {{ $sc }} .parties > tbody > tr > td, {{ $sc }} .notes td { display: block; width: auto !important; padding-right: 0 !important; }
        {{ $sc }} .parties > tbody > tr > td + td { margin-top: 14px; }
        {{ $sc }} .due td { display: block; text-align: left; }
        {{ $sc }} .due .when { padding-top: 0; text-align: left; }
        {{ $sc }} .lines th, {{ $sc }} .lines td { padding-left: 6px; padding-right: 6px; }
        {{ $sc }} .lines .col-tax { display: none; }
        {{ $sc }} .totals { width: 100%; margin-left: 0; }
    }
    @endunless

    /* On paper the page margins do the spacing. */
    @php
        // Paper spacing: tighter than on screen so an ordinary invoice fits on
        // one A4 page. Used when printing from the browser and in the PDF.
        $paperRules = [
            '' => 'font-size: '.($base - 1).'px;',
            '.head-wrap' => 'margin: 0 !important; padding-bottom: 14px;',
            '.logo' => 'max-height: 56px; max-width: 150px;',
            '.mark' => 'width: 44px; height: 44px; line-height: 44px;',
            '.parties' => 'margin-top: 16px;',
            '.due' => 'margin-top: 16px;',
            '.due td' => 'padding: 9px 14px;',
            '.due .amount' => 'font-size: '.($base + 5).'px;',
            '.lines' => 'margin-top: 16px;',
            '.lines th' => 'padding: 6px 8px;',
            '.lines td' => 'padding: 6px 8px;',
            '.totals' => 'margin-top: 8px;',
            '.totals td' => 'padding: 3px 8px;',
            '.totals .grand td' => 'padding-top: 7px; padding-bottom: 6px;',
            '.notes' => 'margin-top: 18px;',
            '.foot' => 'margin-top: 20px; padding-top: 10px;',
        ];
        if ($layout === 'modern') {
            $paperRules['.head-wrap'] = 'margin: 0 !important; padding: 14px 16px;';
        }
    @endphp
    @if ($forPdf)
        {{-- The PDF page margins come from invoices/pdf (@page). --}}
        {{ $sc }} .sheet { padding: 0; }
        @foreach ($paperRules as $sel => $rule)
            {{ $sc }} {{ $sel }} { {!! $rule !!} }
        @endforeach
        {{-- DejaVu, the PDF font, runs larger than the screen lettering. --}}
        {{ $sc }} { font-size: {{ $base - 2 }}px; line-height: 1.4; }
        {{ $sc }} .from-name { font-size: {{ $base + 2 }}px; }
        {{ $sc }} .title { font-size: {{ $compact ? $base + 6 : $base + 10 }}px; }
        {{ $sc }} .due .amount { font-size: {{ $base + 3 }}px; }
        {{ $sc }} .lines td, {{ $sc }} .lines th { padding: 5px 7px; }
        {{-- DomPDF moves a "keep together" block to a new page even when it fits. --}}
        {{ $sc }} .foot, {{ $sc }} .notes { page-break-inside: auto; }
        {{ $sc }} .notes { margin-top: 14px; }
        {{ $sc }} .foot { margin-top: 14px; padding-top: 8px; }
    @else
    /*
     * Printing from the browser. The page itself has no margin (see
     * partials/invoice-print-page) and the sheet carries its own, repeated on
     * every page, so the invoice prints the same whatever margins are picked
     * in the print dialog, nothing is cut at the paper's edge, and the
     * browser adds no date or address lines.
     */
    @media print {
        {{ $sc }} .sheet { padding: 13mm 14mm 12mm; -webkit-box-decoration-break: clone; box-decoration-break: clone; }
        @foreach ($paperRules as $sel => $rule)
            {{ $sc }} {{ $sel }} { {!! $rule !!} }
        @endforeach
    }
    @endif
</style>

<div class="mbdoc {{ ltrim($sc, '.') }}" data-invoice-document>
    <div class="sheet">
        <div class="head-wrap">
            <table class="head">
                <tr>
                    <td style="width: 62%;">
                        {{-- The business's logo (or initials) beside its name and address. --}}
                        <table class="brand">
                            <tr>
                                @if ($logo)
                                    <td class="brand-mark"><img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo" data-testid="invoice-logo"></td>
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
                        <p class="title">Invoice</p>
                        <p class="number">{{ $invoice->invoice_number }}</p>
                        @if ($s['show_status_badge'] && $state['key'] !== 'open')
                            <span class="stamp">{{ $state['label'] }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="parties">
            <tr>
                <td style="width: 55%; padding-right: 24px;">
                    <p class="label">Bill to</p>
                    <p class="to-name">{{ $c?->name }}</p>
                    @foreach ($customerLines as $line)<p class="muted">{{ $line }}</p>@endforeach
                    @if ($c?->tax_number)<p class="muted">TIN: {{ $c->tax_number }}</p>@endif
                </td>
                <td style="width: 45%;">
                    <table class="dates">
                        <tr><td class="k">Invoice date</td><td class="v">{{ $invoice->invoice_date?->format('j M Y') }}</td></tr>
                        <tr><td class="k">Due date</td><td class="v">{{ $invoice->due_date?->format('j M Y') }}</td></tr>
                        @if ($invoice->reference)<tr><td class="k">Reference</td><td class="v">{{ $invoice->reference }}</td></tr>@endif
                        @if ($invoice->waybill_number)<tr><td class="k">Waybill</td><td class="v">{{ $invoice->waybill_number }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="due">
            <tr>
                <td>
                    @if ($state['key'] === 'paid')
                        <p class="label" style="padding-bottom: 2px;">Paid in full</p>
                        <p class="amount">{{ $money($invoice->total) }}</p>
                    @else
                        <p class="label" style="padding-bottom: 2px;">Amount due</p>
                        <p class="amount">{{ $money($state['key'] === 'draft' ? $invoice->total : $balance) }}</p>
                    @endif
                </td>
                <td class="when">
                    @if ($state['key'] === 'paid')
                        Thank you for your payment.
                    @elseif ($state['key'] === 'cancelled')
                        This invoice was cancelled.
                    @else
                        Due by <strong>{{ $invoice->due_date?->format('j M Y') }}</strong>
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
            <tr><td class="muted">Subtotal</td><td class="num">{{ $num($invoice->subtotal) }}</td></tr>
            @if ($hasDiscount)<tr><td class="muted">Discount</td><td class="num">-{{ $num($invoice->discount_amount) }}</td></tr>@endif
            <tr><td class="muted">VAT</td><td class="num">{{ $num($invoice->tax_amount) }}</td></tr>
            <tr class="grand"><td>Total</td><td class="num">{{ $money($invoice->total) }}</td></tr>
            @if ($s['show_payment_info'] && $paid > 0)
                <tr class="paid"><td>Paid</td><td class="num">-{{ $num($paid) }}</td></tr>
            @endif
            @if ($s['show_payment_info'] && $credited > 0)
                <tr class="paid"><td>Credit applied</td><td class="num">-{{ $num($credited) }}</td></tr>
            @endif
            @if ($s['show_payment_info'] && ($paid > 0 || $credited > 0))
                <tr class="balance"><td>Balance due</td><td class="num">{{ $money(max(0, $balance)) }}</td></tr>
            @endif
        </table>

        @php
            $showNotes = $s['show_notes'] && filled($invoice->notes);
            $showTerms = $s['show_terms'] && filled($invoice->terms);
        @endphp
        @if ($showNotes || $showTerms)
            <table class="notes">
                <tr>
                    @if ($showNotes)
                        <td><p class="label">Notes</p><p class="text">{{ $invoice->notes }}</p></td>
                    @endif
                    @if ($showTerms)
                        <td><p class="label">Terms</p><p class="text">{{ $invoice->terms }}</p></td>
                    @endif
                </tr>
            </table>
        @endif

        @if ($invoice->exists)
            @include('e-invoices._print', ['document' => $invoice])
        @endif

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
