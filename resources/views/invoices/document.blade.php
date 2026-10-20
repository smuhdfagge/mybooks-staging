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
    @include('partials.document-styles')
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
