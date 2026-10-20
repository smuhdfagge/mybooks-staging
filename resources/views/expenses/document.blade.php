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
    {{-- "Payment Voucher" is a long title, so it sits smaller than "Invoice". --}}
    @php $titleStep = $compact ? 6 : 10; $titlePdfStep = $compact ? 4 : 7; @endphp
    @include('partials.document-styles')
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
