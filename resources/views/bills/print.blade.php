{{--
    The bill on its own page: for an old bookmark or a link from an email.
    Opens in the same tab, with a way back and Print. The bill page itself
    prints the same document.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Bill {{ $bill->bill_number }} · {{ $tenant->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 antialiased print:bg-white">
    <div class="sticky top-0 z-10 border-b border-gray-200 bg-white/95 backdrop-blur print:hidden">
        <div class="mx-auto flex max-w-[860px] flex-wrap items-center justify-between gap-2 px-4 py-3">
            <a href="{{ route('bills.show', $bill) }}" class="btn-secondary">Back to bill</a>
            <div class="flex items-center gap-2">
                <a href="{{ route('bills.pdf', $bill) }}" class="btn-secondary">Download PDF</a>
                <button type="button" class="btn-primary" data-print>Print</button>
            </div>
        </div>
    </div>
    <main class="mx-auto max-w-[860px] px-0 py-6 sm:px-4 print:max-w-none print:p-0">
        <div class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-lg print:border-0 print:shadow-none">
            @include('bills.document')
        </div>
    </main>
    @include('partials.invoice-print-page')
    @include('partials.dom-actions')
</body>
</html>
