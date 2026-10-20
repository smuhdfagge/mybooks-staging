{{-- A receipt or payment advice as a PDF (DomPDF): the shared document on A4. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} {{ $payment->payment_number }} - {{ $tenant->name }}</title>
    <style>
        /* The same margins as a browser printout; the page number sits in the
           bottom one. DomPDF takes page margins from html, not @page. */
        @page { margin: 0; }
        html { margin: 12mm 14mm 12mm; }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    @include('payments.document')
</body>
</html>
