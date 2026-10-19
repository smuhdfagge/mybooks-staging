{{--
    Printing an invoice (invoice rebuild): an A4 page with even margins, and
    ?print=1 on the address opens the print dialog once the page is ready
    (the "Print" item in the invoice list uses it). The address is then
    tidied so a refresh does not print again.
--}}
<style>
    /* No page margin: the invoice sheet carries its own (invoices/document). */
    @page { size: A4; margin: 0; }
    @media print {
        html, body { background: #fff !important; }
        .mbdoc { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
<script nonce="{{ app('csp-nonce') }}">
    (function () {
        var url = new URL(window.location.href);
        if (url.searchParams.get('print') !== '1') { return; }
        url.searchParams.delete('print');
        window.history.replaceState(null, '', url.pathname + (url.search || '') + url.hash);
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });
    })();
</script>
