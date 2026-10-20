{{--
    The credit note: what is being taken back off an invoice, and how much
    of that credit is still the customer's to use. The same markup is shown
    on the credit note page, printed from it, and turned into the PDF.

    Data from App\Support\CreditNoteDocument::data(): $note, $tenant, $s,
    $font, $forPdf, $logo, $initials, $state, $used, $inWords, $currency.
--}}
@php
    $c = $note->customer;
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
    $lines = $note->items;
    $reason = \App\Models\CreditNote::REASONS[$note->reason] ?? ($note->reason ? ucfirst(str_replace('_', ' ', (string) $note->reason)) : null);
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
    $sc = '.mbdoc-'.substr(md5('cn'.spl_object_id($note).json_encode($s)), 0, 8);
    $stampTone = ['open' => $accent, 'closed' => '#4A5A6B', 'void' => '#B42318', 'draft' => '#4A5A6B'][$state['key']] ?? null;
@endphp
<style>
    {{-- "Credit Note" is a long title, so it sits smaller than "Invoice". --}}
    @php $titleStep = $compact ? 6 : 11; $titlePdfStep = $compact ? 4 : 8; @endphp
    @include('partials.document-styles')
</style>

<div class="mbdoc {{ ltrim($sc, '.') }}" data-credit-note-document>
    <div class="sheet">
        <div class="head-wrap">
            <table class="head">
                <tr>
                    <td style="width: 60%;">
                        <table class="brand">
                            <tr>
                                @if ($logo)
                                    <td class="brand-mark"><img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo" data-testid="credit-note-logo"></td>
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
                        <p class="title">Credit Note</p>
                        <p class="number">{{ $note->credit_note_number }}</p>
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
                    <p class="label">Credit to</p>
                    <p class="to-name">{{ $c?->name }}</p>
                    @foreach ($customerLines as $line)<p class="muted">{{ $line }}</p>@endforeach
                    @if ($c?->tax_number)<p class="muted">TIN: {{ $c->tax_number }}</p>@endif
                </td>
                <td style="width: 45%;">
                    <table class="dates">
                        <tr><td class="k">Date</td><td class="v">{{ $note->credit_note_date?->format('j M Y') }}</td></tr>
                        @if ($note->invoice)<tr><td class="k">Against invoice</td><td class="v">{{ $note->invoice->invoice_number }}</td></tr>@endif
                        @if ($reason)<tr><td class="k">Reason</td><td class="v">{{ $reason }}</td></tr>@endif
                        @if ($note->restock)<tr><td class="k">Goods</td><td class="v">Returned to stock</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="due">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">Credit given</p>
                    <p class="amount">{{ $money($note->total) }}</p>
                </td>
                <td class="when">
                    @if ($state['key'] === 'void')
                        This credit note was cancelled.
                    @elseif ($state['key'] === 'closed')
                        Used up in full.
                    @elseif ($state['key'] === 'draft')
                        Not issued yet.
                    @else
                        <strong>{{ $money($note->balance) }}</strong> still available
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
            <tr><td class="muted">Subtotal</td><td class="num">{{ $num($note->subtotal) }}</td></tr>
            <tr><td class="muted">VAT</td><td class="num">{{ $num($note->tax_amount) }}</td></tr>
            <tr class="grand"><td>Total credit</td><td class="num">{{ $money($note->total) }}</td></tr>
            @if ($used > 0)
                <tr class="paid"><td>Already used</td><td class="num">-{{ $num($used) }}</td></tr>
                <tr class="balance"><td>Still available</td><td class="num">{{ $money(max(0, (float) $note->balance)) }}</td></tr>
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

        @if ($s['show_notes'] && filled($note->notes))
            <table class="notes">
                <tr>
                    {{-- One column, so it takes the full width the shared styles split in two. --}}
                    <td style="width: 100%;"><p class="label">Notes</p><p class="text">{{ $note->notes }}</p></td>
                </tr>
            </table>
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
