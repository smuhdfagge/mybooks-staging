<!DOCTYPE html>
{{-- The sign-in pages are a fixed dark design (navy card on navy), not a
     dark mode, so they stay dark whatever the theme setting (U12). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        @include('partials.pwa-head')

        <title>{{ config('app.name', 'MyBooks') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body {
                font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif;
            }
            /* Rebrand R11: flat MyBooks navy, a solid card, no glows or gradients. */
            .guest-bg {
                background: #0A1B2D;
            }
            .glass-card {
                background: #102A43;
                border: 1px solid #183E61;
            }
            .input-focus-ring:focus {
                box-shadow: 0 0 0 3px rgba(138, 169, 203, 0.35);
            }
            .btn-gradient {
                background: #1F4E79;
                transition: background-color 0.15s ease;
            }
            .btn-gradient:hover {
                background: #3A6798;
            }
        </style>
    </head>
    <body class="antialiased bg-brand-950">
        <div class="min-h-screen flex guest-bg">
            <!-- Left side: who we are -->
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-center px-16 xl:px-24 bg-brand-900 border-r border-brand-800">
                <div class="max-w-md text-white">
                    <div class="flex items-center gap-3 mb-10">
                        <x-brand-mark variant="reversed" class="h-12 w-12" />
                        <span class="text-4xl font-semibold">MyBooks</span>
                    </div>
                    <p class="text-2xl font-medium text-white leading-snug mb-8">Bookkeeping and accounts for Nigerian businesses.</p>
                    <ul class="space-y-4 text-brand-200">
                        <li class="flex items-start gap-3">
                            <span class="mt-2 h-1.5 w-6 rounded-full bg-accent-400 flex-shrink-0" aria-hidden="true"></span>
                            <span>Invoices, bills, stock and payroll in one place</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-2 h-1.5 w-6 rounded-full bg-accent-400 flex-shrink-0" aria-hidden="true"></span>
                            <span>Naira, VAT, PAYE and withholding tax built in</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-2 h-1.5 w-6 rounded-full bg-accent-400 flex-shrink-0" aria-hidden="true"></span>
                            <span>Works on your phone as well as your computer</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right side: the form -->
            <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 sm:p-12">
                <div class="w-full max-w-xl">
                    <!-- Logo on phones -->
                    <div class="lg:hidden flex items-center justify-center gap-3 mb-8">
                        <x-brand-mark variant="reversed" class="h-10 w-10" />
                        <span class="text-3xl font-semibold text-white">MyBooks</span>
                    </div>

                    <!-- Card -->
                    <div class="glass-card rounded-xl shadow-lg p-8 sm:p-10">
                        {{ $slot }}
                    </div>

                    <!-- Footer -->
                    <p class="text-center text-brand-300 text-sm mt-8">
                        &copy; {{ date('Y') }} MyBooks. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Service Worker Registration -->
        <script nonce="{{ app('csp-nonce') }}">
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then((registration) => {
                            console.log('Service Worker registered');
                        })
                        .catch((error) => {
                            console.log('Service Worker registration failed:', error);
                        });
                });
            }
        </script>
        @include('partials.dom-actions')
</body>
</html>
