{{-- Sign-up page shell: a white form beside the navy MyBooks panel. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Create your account · MyBooks</title>
        <meta name="description" content="Open a MyBooks account for your business. Invoices, bills, stock, payroll and tax in one place, made for Nigerian businesses.">
        <link rel="canonical" href="{{ route('register') }}">
        <meta property="og:title" content="Create your MyBooks account">
        <meta property="og:description" content="Bookkeeping and accounts for Nigerian businesses.">
        <meta property="og:image" content="{{ asset('images/brand/mybooks-logo-email-300.png') }}">
        @include('partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-white text-gray-900">
        {{ $slot }}
        @include('partials.dom-actions')
    </body>
</html>
