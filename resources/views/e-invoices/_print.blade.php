{{-- IRN and QR on a printed invoice or credit note, once NRS has accepted it (session 18). --}}
@php
    $eiPrint = config('mybooks.features.e_invoicing') ? \App\Models\EInvoiceSubmission::forDocument($document) : null;
@endphp
@if($eiPrint && $eiPrint->isAccepted())
    <table style="width:100%; margin-top:18px; border:1px solid #e5e7eb; border-collapse:collapse;" data-testid="print-irn">
        <tr>
            <td style="padding:10px; vertical-align:top;">
                <div style="font-size:10px; text-transform:uppercase; color:#666; letter-spacing:.5px;">NRS e-invoice</div>
                <div style="font-size:12px;">IRN: <strong style="font-family:monospace; word-break:break-all;">{{ $eiPrint->irn }}</strong></div>
                @if($eiPrint->accepted_at)<div style="font-size:10px; color:#666;">Cleared by NRS on {{ $eiPrint->accepted_at->format('d M Y, H:i') }}@if($eiPrint->environment && $eiPrint->environment !== 'live') ({{ $eiPrint->environment }} - not a real filing)@endif</div>@endif
            </td>
            @if($qr = $eiPrint->qrSrc())
                <td style="padding:10px; width:110px; text-align:right; vertical-align:top;"><img src="{{ $qr }}" alt="NRS QR code" width="96" height="96" style="width:96px; height:96px;"></td>
            @endif
        </tr>
    </table>
@endif
