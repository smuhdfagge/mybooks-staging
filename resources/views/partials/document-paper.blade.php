{{--
    How a printed document meets the paper. Shared by every document that
    prints (invoice, payment voucher, bill), because this is the part that
    must not drift: it was wrong twice before, once cutting printouts at the
    paper's edge and once losing the PDF's margins.

    Takes $sc (the document's scope class), $forPdf, and $paperRules: the
    selector => rule pairs that tighten the document for paper. The caller
    adds its own rules to that array before including this.
--}}
@if ($forPdf)
    {{-- The PDF's page margins come from the wrapper view's html { margin }. --}}
    {{ $sc }} .sheet { padding: 0; }
    @foreach ($paperRules as $sel => $rule)
        {{ $sc }} {{ $sel }} { {!! $rule !!} }
    @endforeach
@else
/*
 * Printing from the browser. The page itself has no margin (see
 * partials/invoice-print-page) and the sheet carries its own, repeated on
 * every page, so the document prints the same whatever margins are picked
 * in the print dialog, nothing is cut at the paper's edge, and the browser
 * adds no date or address lines.
 */
@media print {
    {{ $sc }} .sheet { padding: 13mm 14mm 12mm; -webkit-box-decoration-break: clone; box-decoration-break: clone; }
    @foreach ($paperRules as $sel => $rule)
        {{ $sc }} {{ $sel }} { {!! $rule !!} }
    @endforeach
}
@endif
