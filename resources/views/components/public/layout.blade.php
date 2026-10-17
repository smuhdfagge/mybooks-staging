{{--
    The shared frame for every public page (home page plan H2): head, top bar,
    footer, dark mode set before paint, and the menu state for phones.

    <x-public.layout title="Pricing" description="...">
        ...sections...
    </x-public.layout>

    A title of null gives just "MyBooks". Pass :nav="false" / :footer="false"
    to leave either out.
--}}
@props([
    'title' => null,
    'description' => 'MyBooks is accounting software for Nigerian businesses: invoices, VAT, WHT, PAYE, payroll, stock and reports, with prices in naira.',
    'nav' => true,
    'footer' => true,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.theme-init')
        <title>{{ $title ? $title.' · MyBooks' : 'MyBooks' }}</title>
        <meta name="description" content="{{ $description }}">
        @include('partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
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
