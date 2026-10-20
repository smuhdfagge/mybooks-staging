{{--
    The bill: the business's own record of what a supplier charged. The
    same markup is shown on the bill page, printed from it, and turned
    into the PDF. It is the sheet that gets filed with the supplier's
    original and passed round for approval before anyone pays.

    Data from App\Support\BillDocument::data(): $bill, $tenant, $s
    (template settings), $font, $forPdf, $logo, $initials, $state, $paid,
    $credited, $inWords, $currency.

    Plain tables and class selectors only, scoped to .mbdoc, so DomPDF draws
    the same page as the browser (no flex, grid or CSS variables).
--}}
@php
    $v = $bill->vendor;
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
    $hasDiscount = (float) $bill->discount_amount > 0;
    $balance = round((float) $bill->balance_due, 2);
    $lines = $bill->items;
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
    // Each document styles only itself, so several can share a page.
    $sc = '.mbdoc-'.substr(md5('bill'.spl_object_id($bill).json_encode($s)), 0, 8);
    $stampTone = ['paid' => $accent, 'overdue' => '#B42318', 'partial' => '#8A5A12', 'draft' => '#4A5A6B', 'cancelled' => '#4A5A6B'][$state['key']] ?? null;
@endphp
<style>
    @include('partials.document-styles')
</style>

<div class="mbdoc {{ ltrim($sc, '.') }}" data-bill-document>
    <div class="sheet">
        <div class="head-wrap">
            <table class="head">
                <tr>
                    <td style="width: 62%;">
                        {{-- The business's logo (or initials) beside its name and address. --}}
                        <table class="brand">
                            <tr>
                                @if ($logo)
                                    <td class="brand-mark"><img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo" data-testid="bill-logo"></td>
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
                        <p class="title">Bill</p>
                        <p class="number">{{ $bill->bill_number }}</p>
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
                    <p class="label">Billed by</p>
                    <p class="to-name">{{ $v?->name ?? 'Not recorded' }}</p>
                    @foreach ($vendorLines as $line)<p class="muted">{{ $line }}</p>@endforeach
                    @if ($v?->tax_number)<p class="muted">TIN: {{ $v->tax_number }}</p>@endif
                </td>
                <td style="width: 45%;">
                    <table class="dates">
                        <tr><td class="k">Bill date</td><td class="v">{{ $bill->bill_date?->format('j M Y') }}</td></tr>
                        <tr><td class="k">Due date</td><td class="v">{{ $bill->due_date?->format('j M Y') }}</td></tr>
                        {{-- The supplier's own number is how they will refer to it. --}}
                        @if ($bill->vendor_bill_number)<tr><td class="k">Their invoice no.</td><td class="v">{{ $bill->vendor_bill_number }}</td></tr>@endif
                        @if ($bill->purchaseOrder)<tr><td class="k">Purchase order</td><td class="v">{{ $bill->purchaseOrder->po_number }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="due">
            <tr>
                <td>
                    @if ($state['key'] === 'paid')
                        <p class="label" style="padding-bottom: 2px;">Paid in full</p>
                        <p class="amount">{{ $money($bill->total) }}</p>
                    @else
                        <p class="label" style="padding-bottom: 2px;">Amount payable</p>
                        <p class="amount">{{ $money($state['key'] === 'draft' ? $bill->total : $balance) }}</p>
                    @endif
                </td>
                <td class="when">
                    @if ($state['key'] === 'paid')
                        Settled in full.
                    @elseif ($state['key'] === 'cancelled')
                        This bill was cancelled.
                    @else
                        Payable by <strong>{{ $bill->due_date?->format('j M Y') }}</strong>
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
            <tr><td class="muted">Subtotal</td><td class="num">{{ $num($bill->subtotal) }}</td></tr>
            @if ($hasDiscount)<tr><td class="muted">Discount</td><td class="num">-{{ $num($bill->discount_amount) }}</td></tr>@endif
            <tr><td class="muted">VAT</td><td class="num">{{ $num($bill->tax_amount) }}</td></tr>
            <tr class="grand"><td>Total</td><td class="num">{{ $money($bill->total) }}</td></tr>
            @if ($s['show_payment_info'] && $paid > 0)
                <tr class="paid"><td>Paid</td><td class="num">-{{ $num($paid) }}</td></tr>
            @endif
            @if ($s['show_payment_info'] && $credited > 0)
                <tr class="paid"><td>Credit applied</td><td class="num">-{{ $num($credited) }}</td></tr>
            @endif
            @if ($s['show_payment_info'] && ($paid > 0 || $credited > 0))
                <tr class="balance"><td>Balance payable</td><td class="num">{{ $money(max(0, $balance)) }}</td></tr>
            @endif
        </table>

        <table class="words">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">Amount in words</p>
                    <p class="text">{{ $inWords }}</p>
                </td>
            </tr>
        </table>

        @if ($s['show_notes'] && filled($bill->notes))
            <table class="notes">
                <tr>
                    {{-- One column, so it takes the full width the shared styles split in two. --}}
                    <td style="width: 100%;"><p class="label">Notes</p><p class="text">{{ $bill->notes }}</p></td>
                </tr>
            </table>
        @endif

        {{-- A bill is checked and approved before anyone pays it. --}}
        <table class="signs">
            <tr>
                <td>
                    <div class="rule"></div>
                    <p class="role">Checked by</p>
                    <p class="who">{{ $bill->createdBy?->name ?? 'Name, signature and date' }}</p>
                </td>
                <td>
                    <div class="rule"></div>
                    <p class="role">Approved by</p>
                    <p class="who">Name, signature and date</p>
                </td>
                <td class="last">
                    <div class="rule"></div>
                    <p class="role">Passed for payment</p>
                    <p class="who">Name, signature and date</p>
                </td>
            </tr>
        </table>

        @if ($s['show_footer'])
            {{-- No invoice footer line here: that one thanks a customer, and
                 a bill is the business's own record. --}}
            <div class="foot">
                <p>
                    @foreach (collect([$tenant->name, $tenant->phone, $tenant->email, $tenant->website ? preg_replace('#^https?://#', '', $tenant->website) : null])->filter()->values() as $bit)
                        @if (! $loop->first)<span class="sep">|</span>@endif<span>{{ $bit }}</span>
                    @endforeach
                </p>
            </div>
        @endif
    </div>
</div>
