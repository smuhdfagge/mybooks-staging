{{--
    Money moving, as a document: a receipt for the customer, or a payment
    advice for the supplier. One sheet, two sides — see
    App\Support\PaymentDocument, which supplies the wording for each.

    Data: $payment, $tenant, $s, $font, $forPdf, $logo, $initials,
    $currency, $kind, $title, $partyLabel, $party, $againstLabel,
    $against, $onAccount, $onAccountLabel, $whtLabel, $whtNote,
    $amountLabel, $settledLabel, $signs, $amount, $wht, $settled,
    $method, $inWords.
--}}
@php
    $money = fn ($value) => \App\Support\Money::format($value, $currency);
    $layout = $s['layout'] ?? 'classic';
    $compact = $layout === 'compact';
    $primary = $s['primary_color'];
    $accent = $s['accent_color'];
    $base = max(10, min(16, (int) $s['font_size'])) - ($compact ? 1 : 0);
    $pad = $compact ? 32 : 48;
    $tenantLines = collect([
        $tenant->address,
        collect([$tenant->city, $tenant->state, $tenant->country])->filter()->implode(', '),
        $tenant->phone,
        $tenant->email,
    ])->filter();
    $partyLines = collect([
        $party?->company_name && $party->company_name !== $party->name ? $party->company_name : null,
        $party?->billing_address ?? $party?->address,
        collect([$party?->city, $party?->state, $party?->postal_code])->filter()->implode(', '),
        $party?->email,
        $party?->phone,
    ])->filter();
    $sc = '.mbdoc-'.substr(md5('pay'.$kind.spl_object_id($payment).json_encode($s)), 0, 8);
    $stampTone = $onAccount ? $primary : $accent;
@endphp
<style>
    {{-- "Payment Advice" is a long title, so it sits smaller than "Receipt". --}}
    @php
        $titleStep = $kind === 'made' ? ($compact ? 5 : 10) : ($compact ? 8 : 14);
        $titlePdfStep = $kind === 'made' ? ($compact ? 4 : 7) : ($compact ? 6 : 10);
    @endphp
    @include('partials.document-styles')
    {{-- Only two or three rows here, and "Settled on the invoice" is a
         long label, so the block is wider than on a lined document. --}}
    {{ $sc }} .totals { width: 58%; margin-left: 42%; }
</style>

<div class="mbdoc {{ ltrim($sc, '.') }}" data-payment-document data-payment-side="{{ $kind }}">
    <div class="sheet">
        <div class="head-wrap">
            <table class="head">
                <tr>
                    <td style="width: 60%;">
                        <table class="brand">
                            <tr>
                                @if ($logo)
                                    <td class="brand-mark"><img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo" data-testid="payment-logo"></td>
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
                        <p class="title">{{ $title }}</p>
                        <p class="number">{{ $payment->payment_number }}</p>
                        @if ($s['show_status_badge'] && $onAccount)
                            <span class="stamp">{{ $onAccountLabel }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="parties">
            <tr>
                <td style="width: 55%; padding-right: 24px;">
                    <p class="label">{{ $partyLabel }}</p>
                    <p class="to-name">{{ $party?->name ?? 'Not recorded' }}</p>
                    @foreach ($partyLines as $line)<p class="muted">{{ $line }}</p>@endforeach
                    @if ($party?->tax_number)<p class="muted">TIN: {{ $party->tax_number }}</p>@endif
                </td>
                <td style="width: 45%;">
                    <table class="dates">
                        <tr><td class="k">Date</td><td class="v">{{ $payment->payment_date?->format('j M Y') }}</td></tr>
                        @if ($method)<tr><td class="k">{{ $kind === 'received' ? 'Paid by' : 'Sent by' }}</td><td class="v">{{ $method }}</td></tr>@endif
                        @if ($payment->bank)<tr><td class="k">{{ $kind === 'received' ? 'Into' : 'From' }}</td><td class="v">{{ $payment->bank->name }}</td></tr>@endif
                        @if ($payment->reference)<tr><td class="k">Reference</td><td class="v">{{ $payment->reference }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="due">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">{{ $amountLabel }}</p>
                    <p class="amount">{{ $money($amount) }}</p>
                </td>
                <td class="when">
                    @if ($onAccount)
                        Held on account, not yet set against a document.
                    @elseif ($against)
                        {{ $againstLabel }} <strong>{{ $against }}</strong>
                    @else
                        {{ $kind === 'received' ? 'Thank you.' : 'With thanks.' }}
                    @endif
                </td>
            </tr>
        </table>

        {{-- What this settles. With WHT there are three figures and people
             get them confused, so each one is named. --}}
        <table class="lines">
            <thead>
                <tr>
                    <th>What this covers</th>
                    <th class="num" style="width: 30%;">Amount ({{ \App\Support\Money::symbol($currency) }})</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <p class="item">
                            @if ($onAccount)
                                {{ $onAccountLabel }}
                            @elseif ($against)
                                {{ $againstLabel }} {{ $against }}
                            @else
                                {{ $kind === 'received' ? 'Payment received' : 'Payment made' }}
                            @endif
                        </p>
                        @if ($payment->notes)<p class="sku">{{ $payment->notes }}</p>@endif
                    </td>
                    <td class="num">{{ number_format($amount, 2) }}</td>
                </tr>
                @if ($wht > 0)
                    <tr>
                        <td>
                            <p class="item">{{ $whtLabel }}</p>
                            <p class="sku">
                                {{ $payment->whtCategory?->name ?? 'Withholding tax' }}
                                at {{ \App\Support\Figure::rate($payment->wht_rate) }} on {{ $money($payment->wht_base) }}@if ($payment->wht_authority), remitted to {{ \App\Services\Accounting\WithholdingTax::authorityLabel((string) $payment->wht_authority, $payment->wht_state) }}@endif
                            </p>
                        </td>
                        <td class="num">{{ number_format($wht, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @if ($wht > 0)
            <table class="totals">
                <tr><td class="muted">{{ $amountLabel }}</td><td class="num">{{ number_format($amount, 2) }}</td></tr>
                <tr><td class="muted">{{ $whtLabel }}</td><td class="num">{{ number_format($wht, 2) }}</td></tr>
                <tr class="grand"><td>{{ $settledLabel }}</td><td class="num">{{ $money($settled) }}</td></tr>
            </table>
        @endif

        <table class="words">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">{{ $amountLabel }} in words</p>
                    <p class="text">{{ $inWords }}</p>
                </td>
            </tr>
        </table>

        @if ($wht > 0)
            <table class="note-strip">
                <tr>
                    <td><p class="muted" style="color: #374151;">{{ $whtNote }}</p></td>
                </tr>
            </table>
        @endif

        <table class="signs">
            <tr>
                @foreach ($signs as $sign)
                    <td @class(['last' => $loop->last]) style="width: {{ round(100 / max(1, count($signs)), 2) }}%;@if ($loop->last) padding-right: 0;@endif">
                        <div class="rule"></div>
                        <p class="role">{{ $sign['role'] }}</p>
                        <p class="who">{{ $sign['who'] }}</p>
                    </td>
                @endforeach
            </tr>
        </table>

        @if ($s['show_footer'])
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
