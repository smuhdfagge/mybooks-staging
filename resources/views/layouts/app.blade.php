<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" x-data="{ dark: document.documentElement.classList.contains('dark') }" x-init="$watch('dark', val => { localStorage.setItem('dark', val); document.documentElement.classList.toggle('dark', val) })">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.theme-init')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="currency-symbol" content="@currencySymbol">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="{{ config('brand.theme_color') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MyBooks">
    <link rel="manifest" href="/manifest.json">
    
    <!-- iOS Touch Icons (multiple sizes for different devices) -->
    <link rel="apple-touch-icon" href="/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/icons/icon-120x120.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/icons/icon-114x114.png">
    <link rel="apple-touch-icon" sizes="76x76" href="/icons/icon-76x76.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="60x60" href="/icons/icon-60x60.png">
    <link rel="apple-touch-icon" sizes="57x57" href="/icons/icon-57x57.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <title>{{ $title ?? config('app.name', 'MyBooks') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles(['nonce' => app('csp-nonce')])
    
    <style>
        /* Hide Alpine.js elements until Alpine processes them */
        [x-cloak] { display: none !important; }
        
        /* Show sidebar on desktop even before Alpine loads */
        @media (min-width: 1024px) {
            aside[x-cloak] {
                display: flex !important;
                transform: translateX(0);
            }
        }
        
        /* Prevent horizontal overflow on mobile */
        html, body {
            overflow-x: hidden;
        }
        /* Smooth transitions for sidebar */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }
        /* Custom scrollbar for sidebar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background-color: rgba(156, 163, 175, 0.7);
        }

        /* Print styles */
        @media print {
            /* Hide non-essential elements */
            .sidebar,
            .no-print,
            header,
            footer,
            nav,
            button,
            .print-hide,
            [x-data*="sidebarOpen"] > div:first-child {
                display: none !important;
            }

            /* Reset main content area */
            .lg\:pl-64 {
                padding-left: 0 !important;
            }

            /* Full width content */
            body {
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Ensure tables print properly */
            table {
                border-collapse: collapse !important;
            }

            th, td {
                border: 1px solid #d1d5db !important;
                padding: 8px !important;
            }

            /* Page breaks */
            .page-break {
                page-break-before: always;
            }

            .avoid-break {
                page-break-inside: avoid;
            }

            /* Print-specific typography */
            h1, h2, h3, h4, h5, h6 {
                page-break-after: avoid;
            }

            /* Reset dark mode colors for print */
            .dark\:bg-gray-800,
            .dark\:bg-gray-900,
            .dark\:bg-gray-700 {
                background-color: white !important;
            }

            .dark\:text-white,
            .dark\:text-gray-100,
            .dark\:text-gray-200,
            .dark\:text-gray-300 {
                color: black !important;
            }

            /* Ensure proper margins */
            @page {
                margin: 1cm;
            }
        }
    </style>
</head>
<body class="h-full font-sans antialiased bg-gray-50 dark:bg-gray-900" x-data="{ sidebarOpen: false }">
    {{-- Skip to main content (accessibility) --}}
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:px-4 focus:py-2 focus:bg-indigo-600 focus:text-white focus:rounded-md focus:shadow-lg focus:outline-none">
        Skip to main content
    </a>

    {{-- Toast notification system --}}
    <x-toast />

    <div class="min-h-screen flex flex-col">
        <!-- Mobile sidebar backdrop -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 bg-gray-900/80 backdrop-blur-sm lg:hidden"
             @click="sidebarOpen = false"
             x-cloak>
        </div>

        <!-- Sidebar -->
        @include('components.sidebar')

        <!-- Main content wrapper -->
        <div class="flex flex-col flex-1 lg:pl-64 min-h-screen">
            <!-- Top header -->
            @include('components.header')

            <!-- Page content -->
            <main id="main-content" class="flex-1 py-4 sm:py-6">
                <div class="mx-auto max-w-7xl px-3 sm:px-4 lg:px-6">
                    <!-- Auto-breadcrumbs (route-driven) -->
                    <x-breadcrumbs />

                    <!-- Page heading -->
                    @if(isset($header))
                    <div class="mb-4 sm:mb-6">
                        {{ $header }}
                    </div>
                    @endif

                    <!-- Flash messages -->
                    @if (session('success'))
                    <div class="mb-4 rounded-lg bg-green-50 p-4 dark:bg-green-900/50" x-data="{ show: true }" x-show="show" x-transition role="alert" aria-live="polite">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
                            </div>
                            <button @click="show = false" class="ml-3 flex-shrink-0 text-green-500 hover:text-green-600">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    @endif

                    @if (session('error'))
                    <div class="mb-4 rounded-lg bg-red-50 p-4 dark:bg-red-900/50" x-data="{ show: true }" x-show="show" x-transition role="alert" aria-live="assertive">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
                            </div>
                            <button @click="show = false" class="ml-3 flex-shrink-0 text-red-500 hover:text-red-600">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    @endif

                    {{-- Form errors, listed once for the whole page (U6) --}}
                    <x-error-summary />

                    <!-- Main content -->
                    <div class="w-full">
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-4 mt-auto">
                <div class="mx-auto max-w-7xl px-3 sm:px-4 lg:px-6">
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        &copy; {{ date('Y') }} {{ config('app.name', 'MyBooks') }}. All rights reserved.
                    </p>
                </div>
            </footer>
        </div>
    </div>

    @livewireScriptConfig(['nonce' => app('csp-nonce')])
    @stack('scripts')
    
    <!-- PWA Install Prompt -->
    <x-pwa-install-prompt />
    
    <!-- Service Worker Registration -->
    <script nonce="{{ app('csp-nonce') }}">
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((registration) => {
                        console.log('Service Worker registered with scope:', registration.scope);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New content is available, you can show a notification
                                    console.log('New content available, please refresh.');
                                }
                            });
                        });
                    })
                    .catch((error) => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }
    </script>
    
    @include('partials.tawk-to')
    @include('partials.dom-actions')
</body>
</html>
