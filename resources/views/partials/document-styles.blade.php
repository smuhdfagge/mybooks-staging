{{--
    How every printable document looks. Shared by the invoice, the payment
    voucher, the bill, the quotation, the credit note and the delivery
    note, so they read as one business's paperwork and a fix reaches all
    of them.

    Plain class selectors only, scoped to this document's $sc, so DomPDF
    draws the same page as the browser (no flex, grid or CSS variables).
    Every document gets the whole set; a rule whose class the document
    does not use costs a few bytes and nothing else.

    The caller works out and passes:
      $sc        this document's scope class, e.g. ".mbdoc-1a2b3c4d"
      $s         the cleaned template settings
      $font, $base, $pad, $compact, $layout, $primary, $accent, $forPdf
      $stampTone the status stamp's colour, or null

    Optional:
      $titleStep      how far above $base the document's title sits
                      (a long title such as "Delivery Note" wants less
                      than a short one such as "Bill")
      $titlePdfStep   the same for the PDF, where DejaVu runs larger
      $extraPaperRules  selector => rule pairs added to the paper rules
--}}
@php
    $titleStep ??= $compact ? 8 : 14;
    $titlePdfStep ??= $compact ? 6 : 10;
    $extraPaperRules ??= [];
@endphp
    {{ $sc }} { font-family: {!! $font !!}; font-size: {{ $base }}px; line-height: 1.5; color: #1F2937; background: #fff; }
    {{ $sc }} * { box-sizing: border-box; }
    {{ $sc }} table { border-collapse: collapse; width: 100%; }
    {{ $sc }} td, {{ $sc }} th { padding: 0; vertical-align: top; }
    {{ $sc }} p { margin: 0; }
    {{ $sc }} .sheet { padding: {{ $pad }}px {{ $pad }}px {{ $compact ? 28 : 36 }}px; }
    {{ $sc }} .muted { color: #5B6B7B; }
    {{ $sc }} .num { text-align: right; white-space: nowrap; }

    /* Head: whose paperwork this is, and what it is */
    {{ $sc }} .head td { vertical-align: top; }
    {{ $sc }} .brand { width: auto; }
    {{ $sc }} .brand-mark { padding-right: 14px; vertical-align: top; }
    {{ $sc }} .logo { display: block; max-height: {{ $compact ? 44 : 64 }}px; max-width: 170px; }
    {{ $sc }} .mark { display: inline-block; width: {{ $compact ? 40 : 52 }}px; height: {{ $compact ? 40 : 52 }}px; line-height: {{ $compact ? 40 : 52 }}px; border-radius: 10px; background: {{ $primary }}; color: #fff; text-align: center; font-weight: bold; font-size: {{ $compact ? $base + 2 : $base + 6 }}px; letter-spacing: 0.5px; }
    {{ $sc }} .from-name { font-size: {{ $base + 4 }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#111827' }}; }
    {{ $sc }} .from-line { font-size: {{ $base - 1 }}px; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#5B6B7B' }}; }
    {{ $sc }} .title { font-size: {{ $base + $titleStep }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : $primary }}; text-align: right; line-height: 1.1; letter-spacing: -0.2px; }
    {{ $sc }} .number { font-size: {{ $base + 1 }}px; font-weight: bold; color: {{ $layout === 'modern' ? $s['header_text_color'] : '#111827' }}; text-align: right; margin-top: 6px; }
    {{ $sc }} .stamp { display: inline-block; margin-top: 8px; padding: 2px 10px; border: 1.5px solid {{ $stampTone ?? '#4A5A6B' }}; color: {{ $stampTone ?? '#4A5A6B' }}; border-radius: 4px; font-size: {{ $base - 2 }}px; font-weight: bold; }
    @if ($layout === 'modern')
        {{ $sc }} .head-wrap { background: {{ $s['header_bg_color'] }}; margin: -{{ $pad }}px -{{ $pad }}px 0; padding: {{ $pad }}px {{ $pad }}px {{ $compact ? 20 : 28 }}px; border-bottom: {{ max(1, (int) $s['border_width']) }}px {{ $s['border_style'] }} {{ $primary }}; }
    @elseif ($layout === 'minimal')
        {{ $sc }} .head-wrap { padding-bottom: {{ $compact ? 16 : 24 }}px; border-bottom: 1px solid #E5E7EB; }
    @else
        {{ $sc }} .head-wrap { padding-bottom: {{ $compact ? 16 : 24 }}px; border-bottom: {{ max(1, (int) $s['border_width']) }}px {{ $s['border_style'] }} {{ $primary }}; }
    @endif

    /* Who it concerns, and the particulars */
    {{ $sc }} .parties { margin-top: {{ $compact ? 18 : 28 }}px; }
    {{ $sc }} .label { font-size: {{ $base - 2 }}px; color: #5B6B7B; padding-bottom: 4px; }
    {{ $sc }} .to-name { font-size: {{ $base + 2 }}px; font-weight: bold; color: #111827; }
    {{ $sc }} .dates td { padding: 2px 0; }
    {{ $sc }} .dates .k { color: #5B6B7B; padding-right: 18px; }
    {{ $sc }} .dates .v { text-align: right; font-weight: bold; color: #111827; white-space: nowrap; }

    /* The figure that matters, at a glance */
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
    {{-- Short against what was ordered: that is what gets disputed. --}}
    {{ $sc }} .lines td.short { color: #B42318; font-weight: bold; }
    {{ $sc }} .warn { color: #B42318; font-weight: bold; }
    {{ $sc }} .note-strip { margin-top: {{ $compact ? 16 : 22 }}px; border: 1px solid {{ $s['table_border_color'] }}; page-break-inside: avoid; }
    {{ $sc }} .note-strip td { padding: {{ $compact ? '9px 12px' : '11px 14px' }}; }
    {{ $sc }} .sku { font-size: {{ $base - 2 }}px; color: #5B6B7B; }

    /* Totals, closed with the accountant's double rule */
    {{ $sc }} .totals { width: 46%; margin-left: 54%; margin-top: 14px; page-break-inside: avoid; }
    {{ $sc }} .totals td { padding: 4px 10px; }
    {{ $sc }} .totals .grand td { padding-top: 10px; padding-bottom: 8px; font-size: {{ $base + 3 }}px; font-weight: bold; color: #111827; border-top: 1px solid #D1D5DB; }
    {{ $sc }} .totals .grand td.num { border-bottom: 3px double {{ $primary }}; }
    {{ $sc }} .totals .paid td { color: {{ $accent }}; }
    {{ $sc }} .totals .balance td { font-weight: bold; color: #111827; background: #F3F6F9; padding-top: 8px; padding-bottom: 8px; }

    /* The amount written out, as a voucher or cheque carries it */
    {{ $sc }} .words { margin-top: {{ $compact ? 16 : 22 }}px; border: 1px solid {{ $s['table_border_color'] }}; page-break-inside: avoid; }
    {{ $sc }} .words td { padding: {{ $compact ? '9px 12px' : '11px 14px' }}; }
    {{ $sc }} .words .text { font-weight: bold; color: #111827; }

    /* Notes and terms */
    {{ $sc }} .notes { margin-top: {{ $compact ? 22 : 32 }}px; page-break-inside: avoid; }
    {{ $sc }} .notes td { width: 50%; padding-right: 18px; }
    {{ $sc }} .notes .text { white-space: pre-line; color: #374151; font-size: {{ $base - 1 }}px; }

    /* Who prepared it, who approved it, who received it */
    {{ $sc }} .signs { margin-top: {{ $compact ? 26 : 38 }}px; page-break-inside: avoid; }
    {{ $sc }} .signs td { width: 33.33%; padding-right: 18px; }
    {{ $sc }} .signs td.last { padding-right: 0; }
    {{ $sc }} .signs .rule { border-bottom: 1px solid #9AA7B4; height: {{ $compact ? 30 : 38 }}px; }
    {{ $sc }} .signs .role { font-size: {{ $base - 2 }}px; font-weight: bold; color: #111827; padding-top: 5px; }
    {{ $sc }} .signs .who { font-size: {{ $base - 2 }}px; color: #5B6B7B; }

    {{ $sc }} .foot { margin-top: {{ $compact ? 24 : 36 }}px; padding-top: 14px; border-top: 1px solid #E5E7EB; text-align: center; color: {{ $s['footer_text_color'] }}; font-size: {{ $base - 1 }}px; page-break-inside: avoid; }
    {{ $sc }} .foot .sep { padding: 0 8px; color: #CBD5E1; }
    {{ $sc }} .foot .thanks { color: {{ $primary }}; font-weight: bold; font-size: {{ $base }}px; margin-bottom: 2px; }

    @unless ($forPdf)
    /* A phone: the same document, reflowed to fit (screen only). */
    @media screen and (max-width: 640px) {
        {{ $sc }} .sheet { padding: 22px 16px 24px; }
        {{ $sc }} .head-wrap { margin-left: {{ $layout === 'modern' ? '-16px' : '0' }} !important; margin-right: {{ $layout === 'modern' ? '-16px' : '0' }} !important; margin-top: {{ $layout === 'modern' ? '-22px' : '0' }} !important; }
        {{ $sc }} .title { font-size: {{ $base + 8 }}px; }
        {{ $sc }} .logo { max-width: 84px; max-height: 44px; }
        {{ $sc }} .brand-mark { padding-right: 10px; }
        {{ $sc }} .parties > tbody > tr > td, {{ $sc }} .notes td, {{ $sc }} .signs td { display: block; width: auto !important; padding-right: 0 !important; }
        {{ $sc }} .parties > tbody > tr > td + td, {{ $sc }} .signs td + td { margin-top: 14px; }
        {{ $sc }} .due td { display: block; text-align: left; }
        {{ $sc }} .due .when { padding-top: 0; text-align: left; }
        {{ $sc }} .lines th, {{ $sc }} .lines td { padding-left: 6px; padding-right: 6px; }
        {{ $sc }} .lines .col-tax { display: none; }
        {{ $sc }} .totals { width: 100%; margin-left: 0; }
    }
    @endunless

    @php
        // Paper spacing: tighter than on screen so an ordinary document fits
        // on one A4 page. Used when printing from the browser and in the PDF.
        $paperRules = array_merge([
            '' => 'font-size: '.($base - 1).'px;',
            '.head-wrap' => $layout === 'modern'
                ? 'margin: 0 !important; padding: 14px 16px;'
                : 'margin: 0 !important; padding-bottom: 14px;',
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
            '.notes' => 'margin-top: 18px;',
            '.signs' => 'margin-top: 26px;',
            '.foot' => 'margin-top: 20px; padding-top: 10px;',
        ], $extraPaperRules);
    @endphp
    @include('partials.document-paper')
    @if ($forPdf)
        {{-- DejaVu, the PDF font, runs larger than the screen lettering. --}}
        {{ $sc }} { font-size: {{ $base - 2 }}px; line-height: 1.4; }
        {{ $sc }} .from-name { font-size: {{ $base + 2 }}px; }
        {{ $sc }} .title { font-size: {{ $base + $titlePdfStep }}px; }
        {{ $sc }} .due .amount { font-size: {{ $base + 3 }}px; }
        {{ $sc }} .lines td, {{ $sc }} .lines th { padding: 5px 7px; }
        {{-- DomPDF moves a "keep together" block to a new page even when it fits. --}}
        {{ $sc }} .foot, {{ $sc }} .notes, {{ $sc }} .signs, {{ $sc }} .words { page-break-inside: auto; }
        {{ $sc }} .notes { margin-top: 14px; }
        {{ $sc }} .signs { margin-top: 22px; }
        {{ $sc }} .foot { margin-top: 14px; padding-top: 8px; }
    @endif
