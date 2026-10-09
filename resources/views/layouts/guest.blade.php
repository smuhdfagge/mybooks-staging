<!DOCTYPE html>
{{-- The sign-in pages are a fixed dark design (dark card on a dark gradient), not a
     dark mode, so they stay dark whatever the theme setting (U12). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
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

        <title>{{ config('app.name', 'MyBooks') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body {
                font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif;
            }
            .gradient-bg {
                background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);
            }
            .glass-card {
                background: rgba(30, 41, 59, 0.95);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(148, 163, 184, 0.1);
            }
            .input-focus-ring:focus {
                box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.3);
            }
            .btn-gradient {
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
                transition: all 0.3s ease;
            }
            .btn-gradient:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(139, 92, 246, 0.4);
            }
            .floating-shapes {
                position: absolute;
                width: 100%;
                height: 100%;
                overflow: hidden;
                z-index: 0;
            }
            .shape {
                position: absolute;
                border-radius: 50%;
                background: rgba(139, 92, 246, 0.15);
                animation: float 8s ease-in-out infinite;
            }
            .shape-1 {
                width: 80px;
                height: 80px;
                top: 10%;
                left: 10%;
                animation-delay: 0s;
            }
            .shape-2 {
                width: 120px;
                height: 120px;
                top: 60%;
                right: 10%;
                animation-delay: 2s;
            }
            .shape-3 {
                width: 60px;
                height: 60px;
                bottom: 20%;
                left: 20%;
                animation-delay: 4s;
            }
            .shape-4 {
                width: 100px;
                height: 100px;
                top: 30%;
                right: 25%;
                animation-delay: 1s;
            }
            .shape-5 {
                width: 40px;
                height: 40px;
                top: 50%;
                left: 5%;
                animation-delay: 3s;
                background: rgba(168, 85, 247, 0.1);
            }
            @keyframes float {
                0%, 100% {
                    transform: translateY(0) rotate(0deg);
                }
                50% {
                    transform: translateY(-20px) rotate(10deg);
                }
            }
            .logo-glow {
                filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.5));
            }
        </style>
    </head>
    <body class="antialiased bg-slate-900">
        <div class="min-h-screen flex gradient-bg relative">
            <!-- Floating Shapes -->
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
                <div class="shape shape-4"></div>
                <div class="shape shape-5"></div>
            </div>

            <!-- Left Side - Branding -->
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-center items-center p-12 relative z-10">
                <div class="text-center text-white">
                    <!-- MyBooks Logo -->
                    <div class="mb-8 logo-glow">
                        <svg class="w-28 h-28 mx-auto text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h1 class="text-5xl font-bold mb-4 bg-gradient-to-r from-violet-400 via-purple-400 to-fuchsia-400 bg-clip-text text-transparent">MyBooks</h1>
                    <p class="text-xl text-slate-300 mb-10">Manage your business finances with ease</p>
                    <div class="flex justify-center space-x-10 text-slate-400">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-violet-400">100%</div>
                            <div class="text-sm">Secure</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-purple-400">24/7</div>
                            <div class="text-sm">Access</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-fuchsia-400">Easy</div>
                            <div class="text-sm">To Use</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Form -->
            <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 sm:p-12 relative z-10">
                <div class="w-full max-w-xl">
                    <!-- Mobile Logo -->
                    <div class="lg:hidden text-center mb-8">
                        <svg class="w-20 h-20 mx-auto logo-glow" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Background Circle -->
                            <circle cx="60" cy="60" r="55" fill="url(#logoGradientMobile)" fill-opacity="0.2"/>
                            <circle cx="60" cy="60" r="55" stroke="url(#logoGradientMobile)" stroke-width="2"/>
                            
                            <!-- Book Stack -->
                            <rect x="30" y="70" width="60" height="12" rx="2" fill="#a78bfa"/>
                            <rect x="33" y="56" width="54" height="12" rx="2" fill="#8b5cf6"/>
                            <rect x="36" y="42" width="48" height="12" rx="2" fill="#7c3aed"/>
                            
                            <!-- Open Book on Top -->
                            <path d="M60 28C60 28 45 32 38 35V50C45 47 60 44 60 44C60 44 75 47 82 50V35C75 32 60 28 60 28Z" fill="#c4b5fd" stroke="#a78bfa" stroke-width="1.5"/>
                            <path d="M60 28V44" stroke="#8b5cf6" stroke-width="1.5"/>
                            
                            <!-- Dollar Sign -->
                            <circle cx="82" cy="75" r="12" fill="#10b981"/>
                            <text x="82" y="80" text-anchor="middle" fill="white" font-size="14" font-weight="bold">$</text>
                            
                            <defs>
                                <linearGradient id="logoGradientMobile" x1="0" y1="0" x2="120" y2="120" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#8b5cf6"/>
                                    <stop offset="1" stop-color="#a855f7"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <h1 class="text-3xl font-bold mt-4 bg-gradient-to-r from-violet-400 via-purple-400 to-fuchsia-400 bg-clip-text text-transparent">MyBooks</h1>
                    </div>

                    <!-- Card -->
                    <div class="glass-card rounded-2xl shadow-2xl p-8 sm:p-10">
                        {{ $slot }}
                    </div>

                    <!-- Footer -->
                    <p class="text-center text-slate-500 text-sm mt-8">
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
