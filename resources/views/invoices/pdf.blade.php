{{-- The invoice as a PDF (DomPDF): the shared document on an A4 page. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }} - {{ $tenant->name }}</title>
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { padding: 26px 20px 46px; }
    </style>
</head>
<body>
    @include('invoices.document')
</body>
</html>
