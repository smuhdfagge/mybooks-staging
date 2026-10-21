{{--
    The shared frame for every public page (home page plan H2): head, top bar,
    footer, dark mode set before paint, and the menu state for phones.

    <x-public.layout title="Pricing" description="...">
        ...sections...
    </x-public.layout>

    A title of null gives just "MyBooks". Pass :nav="false" / :footer="false"
    to leave either out. Every page gets a canonical address and share tags
    (H5); pass :schema="[...]" for structured data.
--}}
@props([
    'title' => null,
    'description' => 'MyBooks is accounting software for Nigerian businesses: invoices, VAT, WHT, PAYE, payroll, stock and reports, with prices in naira.',
    'nav' => true,
    'footer' => true,
    // Share picture for WhatsApp, Facebook, X and LinkedIn (1200 x 630).
    'image' => 'images/share/mybooks-share.png',
    // Structured data for search engines (an array, written as JSON-LD).
    'schema' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.theme-init')
        @php
            $fullTitle = $title ? $title.' · MyBooks' : 'MyBooks';
            $canonical = url()->current();
            $shareImage = str_starts_with($image, 'http') ? $image : asset($image);
        @endphp
        <title>{{ $fullTitle }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">
        @unless (config('mybooks.search_indexing'))
            <meta name="robots" content="noindex, nofollow">
        @endunless
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="MyBooks">
        <meta property="og:locale" content="en_NG">
        <meta property="og:title" content="{{ $fullTitle }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $shareImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="MyBooks: invoices out, money in, tax worked out.">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $fullTitle }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $shareImage }}">
        @if ($schema)
            <script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endif
        @include('partials.pwa-head')
        @include('partials.analytics')
        {{-- public.js is Alpine only; the app's own script is not needed here (H5). --}}
        @vite(['resources/css/app.css', 'resources/js/public.js'])
        {{-- Page extras after the main stylesheet, so they win a tie. --}}
        @isset($head){{ $head }}@endisset
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="bg-white text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100" x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-brand-700 focus:shadow">Skip to the page</a>

        @if ($nav)
            <x-public.nav />
        @endif

        <main id="main" {{ $attributes }}>
            {{ $slot }}
        </main>

        @if ($footer)
            <x-public.footer />
        @endif
    </body>
</html>
