{{--
    The payment voucher: the document a business files and signs for money
    going out, the counterpart of the invoice for money coming in. The same
    markup is shown on the expense page, printed from it, and turned into
    the PDF.

    Data from App\Support\ExpenseDocument::data(): $expense, $tenant, $s
    (template settings), $font, $forPdf, $logo, $initials, $state, $inWords,
    $currency.

    Plain tables and class selectors only, scoped to .mbdoc, so DomPDF draws
    the same page as the browser (no flex, grid or CSS variables).
--}}
@php
    $v = $expense->vendor;
    $sym = \App\Support\Money::symbol($currency);
    $money = fn ($value) => \App\Support\Money::format($value, $currency);
    $num = fn ($value) => number_format((float) $value, 2);
    $layout = $s['layout'] ?? 'classic';
    $compact = $layout === 'compact';
    $primary = $s['primary_color'];
    $accent = $s['accent_color'];
    $base = max(10, min(16, (int) $s['font_size'])) - ($compact ? 1 : 0);
    $pad = $compact ? 32 : 48;
    $hasTax = (float) $expense->tax_amount > 0;
    $tenantLines = collect([
        $tenant->address,
        collect([$tenant->city, $tenant->state, $tenant->country])->filter()->implode(', '),
        $tenant->phone,
        $tenant->email,
    ])->filter();
    $payeeLines = collect([
        $v?->company_name && $v->company_name !== $v->name ? $v->company_name : null,
        $v?->address,
        collect([$v?->city, $v?->state, $v?->postal_code])->filter()->implode(', '),
        $v?->email,
        $v?->phone,
    ])->filter();
    $method = $expense->payment_method
        ? ucfirst(str_replace('_', ' ', (string) $expense->payment_method))
        : null;
    // Each document styles only itself, so several can share a page.
    $sc = '.mbdoc-'.substr(md5('exp'.spl_object_id($expense).json_encode($s)), 0, 8);
    $stampTone = ['paid' => $accent, 'rejected' => '#B42318', 'pending' => '#8A5A12', 'draft' => '#4A5A6B', 'approved' => $primary][$state['key']] ?? null;
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

    /* Head: who is paying, and what this is */
    {{ $sc }} .head td { vertical-align: top; }
    {{ $sc }} .brand { width: auto; }
    {{ $sc }} .brand-mark { padding-right: 14px; vertical-align: top; }
    {{ $sc }} .logo { display: block; max-height: {{ $compact ? 44 : 64 }}px; max-width: 170px; }
    {{ $sc }} .mark { display: inline-block; width: {{ $compact ? 40 : 52 }}px; height: {{ $compact ? 40 : 52 }}px; line-height: {{ $compact ? 40 : 52 }}px; border-radius: 10px; background: {{ $primary }}; color: #fff; text-align: center; font-weight: bold; font-size: {{ $compact ? $base + 2 : $base + 6 }}px; letter-spacing: 0.5px; }
    {{ $sc }} .from-name { font-size: {{ $base + 4 }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#111827' }}; }
    {{ $sc }} .from-line { font-size: {{ $base - 1 }}px; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#5B6B7B' }}; }
    {{ $sc }} .title { font-size: {{ $compact ? $base + 6 : $base + 10 }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : $primary }}; text-align: right; line-height: 1.1; letter-spacing: -0.2px; }
    {{ $sc }} .number { font-size: {{ $base + 1 }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#111827' }}; text-align: right; margin-top: 6px; }
    {{ $sc }} .stamp { display: inline-block; margin-top: 8px; padding: 2px 10px; border: 1.5px solid {{ $stampTone ?? '#4A5A6B' }}; color: {{ $stampTone ?? '#4A5A6B' }}; border-radius: 4px; font-size: {{ $base - 2 }}px; font-weight: bold; }
    @if ($layout === 'modern')
        {{ $sc }} .head-wrap { background: {{ $s['header_bg_color'] }}; margin: -{{ $pad }}px -{{ $pad }}px 0; padding: {{ $pad }}px {{ $pad }}px {{ $compact ? 20 : 28 }}px; border-bottom: {{ max(1, (int) $s['border_width']) }}px {{ $s['border_style'] }} {{ $primary }}; }
    @elseif ($layout === 'minimal')
        {{ $sc }} .head-wrap { padding-bottom: {{ $compact ? 16 : 24 }}px; border-bottom: 1px solid #E5E7EB; }
    @else
        {{ $sc }} .head-wrap { padding-bottom: {{ $compact ? 16 : 24 }}px; border-bottom: {{ max(1, (int) $s['border_width']) }}px {{ $s['border_style'] }} {{ $primary }}; }
    @endif

    /* Payee and the particulars of the payment */
    {{ $sc }} .parties { margin-top: {{ $compact ? 18 : 28 }}px; }
    {{ $sc }} .label { font-size: {{ $base - 2 }}px; color: #5B6B7B; padding-bottom: 4px; }
    {{ $sc }} .to-name { font-size: {{ $base + 2 }}px; font-weight: bold; color: #111827; }
    {{ $sc }} .dates td { padding: 2px 0; }
    {{ $sc }} .dates .k { color: #5B6B7B; padding-right: 18px; }
    {{ $sc }} .dates .v { text-align: right; font-weight: bold; color: #111827; }

    /* What was paid, at a glance */
    {{ $sc }} .due { margin-top: {{ $compact ? 18 : 26 }}px; background: #F3F6F9; border-left: 4px solid {{ $primary }}; }
    {{ $sc }} .due td { padding: {{ $compact ? '10px 14px' : '14px 18px' }}; vertical-align: middle; }
    {{ $sc }} .due .amount { font-size: {{ $base + 9 }}px; font-weight: bold; color: #111827; }
    {{ $sc }} .due .when { text-align: right; color: #374151; }

    /* Particulars */
    {{ $sc }} .lines { margin-top: {{ $compact ? 18 : 26 }}px; }
    {{ $sc }} .lines th { background: {{ $s['table_header_bg'] }}; color: {{ $s['table_header_text'] }}; font-size: {{ $base - 2 }}px; font-weight: bold; text-align: left; padding: 8px 10px; border-bottom: 1px solid {{ $s['table_border_color'] }}; }
    {{ $sc }} .lines th.num { text-align: right; }
    {{ $sc }} .lines td { padding: {{ $compact ? '7px 10px' : '10px 10px' }}; border-bottom: 1px solid {{ $s['table_border_color'] }}; }
    {{ $sc }} .lines tr { page-break-inside: avoid; }
    {{ $sc }} .item { font-weight: bold; color: #111827; }
    {{ $sc }} .sku { font-size: {{ $base - 2 }}px; color: #5B6B7B; }

    /* Totals, closed with the accountant's double rule */
    {{ $sc }} .totals { width: 46%; margin-left: 54%; margin-top: 14px; page-break-inside: avoid; }
    {{ $sc }} .totals td { padding: 4px 10px; }
    {{ $sc }} .totals .grand td { padding-top: 10px; padding-bottom: 8px; font-size: {{ $base + 3 }}px; font-weight: bold; color: #111827; border-top: 1px solid #D1D5DB; }
    {{ $sc }} .totals .grand td.num { border-bottom: 3px double {{ $primary }}; }

    /* The amount written out, as a voucher carries it */
    {{ $sc }} .words { margin-top: {{ $compact ? 16 : 22 }}px; border: 1px solid {{ $s['table_border_color'] }}; page-break-inside: avoid; }
    {{ $sc }} .words td { padding: {{ $compact ? '9px 12px' : '11px 14px' }}; }
    {{ $sc }} .words .text { font-weight: bold; color: #111827; }

    /* Notes */
    {{ $sc }} .notes { margin-top: {{ $compact ? 18 : 26 }}px; page-break-inside: avoid; }
    {{ $sc }} .notes td { width: 50%; padding-right: 18px; }
    {{ $sc }} .notes .text { white-space: pre-line; color: #374151; font-size: {{ $base - 1 }}px; }

    /* Who prepared it, who approved it, who received the money */
    {{ $sc }} .signs { margin-top: {{ $compact ? 26 : 38 }}px; page-break-inside: avoid; }
    {{ $sc }} .signs td { width: 33.33%; padding-right: 18px; }
    {{ $sc }} .signs td.last { padding-right: 0; }
    {{ $sc }} .signs .rule { border-bottom: 1px solid #9AA7B4; height: {{ $compact ? 30 : 38 }}px; }
    {{ $sc }} .signs .role { font-size: {{ $base - 2 }}px; font-weight: bold; color: #111827; padding-top: 5px; }
    {{ $sc }} .signs .who { font-size: {{ $base - 2 }}px; color: #5B6B7B; }

    {{ $sc }} .foot { margin-top: {{ $compact ? 22 : 32 }}px; padding-top: 14px; border-top: 1px solid #E5E7EB; text-align: center; color: {{ $s['footer_text_color'] }}; font-size: {{ $base - 1 }}px; page-break-inside: avoid; }
    {{ $sc }} .foot .sep { padding: 0 8px; color: #CBD5E1; }

    @unless ($forPdf)
    /* A phone: the same voucher, reflowed to fit (screen only). */
    @media screen and (max-width: 640px) {
        {{ $sc }} .sheet { padding: 22px 16px 24px; }
        {{ $sc }} .head-wrap { margin-left: {{ $layout === 'modern' ? '-16px' : '0' }} !important; margin-right: {{ $layout === 'modern' ? '-16px' : '0' }} !important; margin-top: {{ $layout === 'modern' ? '-22px' : '0' }} !important; }
        {{ $sc }} .title { font-size: {{ $base + 6 }}px; }
        {{ $sc }} .logo { max-width: 84px; max-height: 44px; }
        {{ $sc }} .brand-mark { padding-right: 10px; }
        {{ $sc }} .parties > tbody > tr > td, {{ $sc }} .notes td, {{ $sc }} .signs td { display: block; width: auto !important; padding-right: 0 !important; }
        {{ $sc }} .parties > tbody > tr > td + td, {{ $sc }} .signs td + td { margin-top: 14px; }
        {{ $sc }} .due td { display: block; text-align: left; }
        {{ $sc }} .due .when { padding-top: 0; text-align: left; }
        {{ $sc }} .lines th, {{ $sc }} .lines td { padding-left: 6px; padding-right: 6px; }
        {{ $sc }} .totals { width: 100%; margin-left: 0; }
    }
    @endunless

    @php
        // Paper spacing: tighter than on screen so an ordinary voucher fits
        // on one A4 page. Used when printing from the browser and in the PDF.
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
            '.words' => 'margin-top: 14px;',
            '.words td' => 'padding: 8px 12px;',
            '.notes' => 'margin-top: 16px;',
            '.signs' => 'margin-top: 26px;',
            '.foot' => 'margin-top: 20px; padding-top: 10px;',
        ];
        if ($layout === 'modern') {
            $paperRules['.head-wrap'] = 'margin: 0 !important; padding: 14px 16px;';
        }
    @endphp
    @include('partials.document-paper')
    @if ($forPdf)
        {{-- DejaVu, the PDF font, runs larger than the screen lettering. --}}
        {{ $sc }} { font-size: {{ $base - 2 }}px; line-height: 1.4; }
        {{ $sc }} .from-name { font-size: {{ $base + 2 }}px; }
        {{ $sc }} .title { font-size: {{ $compact ? $base + 4 : $base + 7 }}px; }
        {{ $sc }} .due .amount { font-size: {{ $base + 3 }}px; }
        {{ $sc }} .lines td, {{ $sc }} .lines th { padding: 5px 7px; }
        {{-- DomPDF moves a "keep together" block to a new page even when it fits. --}}
        {{ $sc }} .foot, {{ $sc }} .notes, {{ $sc }} .signs, {{ $sc }} .words { page-break-inside: auto; }
        {{ $sc }} .signs { margin-top: 22px; }
        {{ $sc }} .foot { margin-top: 14px; padding-top: 8px; }
    @endif
</style>

<div class="mbdoc {{ ltrim($sc, '.') }}" data-expense-document>
    <div class="sheet">
        <div class="head-wrap">
            <table class="head">
                <tr>
                    <td style="width: 60%;">
                        {{-- The business's logo (or initials) beside its name and address. --}}
                        <table class="brand">
                            <tr>
                                @if ($logo)
                                    <td class="brand-mark"><img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo" data-testid="expense-logo"></td>
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
                        <p class="title">Payment Voucher</p>
                        <p class="number">{{ $expense->expense_number }}</p>
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
                    <p class="label">Pay to</p>
                    <p class="to-name">{{ $v?->name ?? 'Not recorded' }}</p>
                    @foreach ($payeeLines as $line)<p class="muted">{{ $line }}</p>@endforeach
                    @if ($v?->tax_number)<p class="muted">TIN: {{ $v->tax_number }}</p>@endif
                </td>
                <td style="width: 45%;">
                    <table class="dates">
                        <tr><td class="k">Date</td><td class="v">{{ $expense->expense_date?->format('j M Y') }}</td></tr>
                        @if ($method)<tr><td class="k">Paid by</td><td class="v">{{ $method }}</td></tr>@endif
                        @if ($expense->paidThroughAccount)<tr><td class="k">Paid from</td><td class="v">{{ $expense->paidThroughAccount->name }}</td></tr>@endif
                        @if ($expense->reference)<tr><td class="k">Reference</td><td class="v">{{ $expense->reference }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="due">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">{{ $state['key'] === 'paid' ? 'Amount paid' : 'Amount payable' }}</p>
                    <p class="amount">{{ $money($expense->total) }}</p>
                </td>
                <td class="when">
                    @if ($state['key'] === 'paid')
                        Paid on {{ $expense->expense_date?->format('j M Y') }}
                    @elseif ($state['key'] === 'rejected')
                        This voucher was rejected.
                    @elseif ($state['key'] === 'approved')
                        Approved for payment
                    @else
                        Not yet approved for payment
                    @endif
                </td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th>Particulars</th>
                    <th style="width: 30%;">Charged to</th>
                    <th class="num" style="width: 22%;">Amount ({{ $sym }})</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        @php
                            $heading = $expense->name ?: ($expense->description ?: 'Expense');
                            // The description only earns a second line when it says something new.
                            $detail = filled($expense->description) && trim((string) $expense->description) !== trim((string) $heading)
                                ? $expense->description
                                : null;
                        @endphp
                        <p class="item">{{ $heading }}</p>
                        @if ($detail)<p class="sku">{{ $detail }}</p>@endif
                    </td>
                    <td>{{ $expense->expenseAccount?->name ?? '—' }}</td>
                    <td class="num">{{ $num($expense->amount) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="totals">
            @if ($hasTax)
                <tr><td class="muted">Subtotal</td><td class="num">{{ $num($expense->amount) }}</td></tr>
                <tr><td class="muted">VAT</td><td class="num">{{ $num($expense->tax_amount) }}</td></tr>
            @endif
            <tr class="grand"><td>Total</td><td class="num">{{ $money($expense->total) }}</td></tr>
        </table>

        <table class="words">
            <tr>
                <td>
                    <p class="label" style="padding-bottom: 2px;">Amount in words</p>
                    <p class="text">{{ $inWords }}</p>
                </td>
            </tr>
        </table>

        @if ($expense->notes)
            <table class="notes">
                <tr>
                    <td style="width: 100%;"><p class="label">Notes</p><p class="text">{{ $expense->notes }}</p></td>
                </tr>
            </table>
        @endif

        {{-- Signed against the written amount, so the figure cannot be altered later. --}}
        <table class="signs">
            <tr>
                <td>
                    <div class="rule"></div>
                    <p class="role">Prepared by</p>
                    <p class="who">{{ $expense->createdBy?->name ?? 'Name, signature and date' }}</p>
                </td>
                <td>
                    <div class="rule"></div>
                    <p class="role">Approved by</p>
                    <p class="who">
                        @if ($expense->approvedByUser)
                            {{ $expense->approvedByUser->name }}@if ($expense->approved_at), {{ $expense->approved_at->format('j M Y') }}@endif
                        @else
                            Name, signature and date
                        @endif
                    </p>
                </td>
                <td class="last">
                    <div class="rule"></div>
                    <p class="role">Received by</p>
                    <p class="who">Name, signature and date</p>
                </td>
            </tr>
        </table>

        @if ($s['show_footer'])
            {{-- No invoice footer line here: that one thanks a customer, and
                 a voucher is the business's own record. --}}
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
