<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>About Us - {{ config('app.name', 'MyBooks') }}</title>
        <meta name="description" content="Learn more about MyBooks - our mission, values, and the team behind the complete accounting and bookkeeping solution.">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .gradient-text { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        </style>
    </head>
    <body class="bg-white dark:bg-gray-900 antialiased" x-data="{ mobileMenuOpen: false }">
        
        <!-- Navigation -->
        <nav class="fixed w-full bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <a href="{{ route('home') }}" class="text-2xl font-bold gradient-text">MyBooks</a>
                    </div>
                    
                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-8">
                        <a href="{{ route('home') }}#features" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Features</a>
                        <a href="{{ route('home') }}#how-it-works" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">How It Works</a>
                        <a href="{{ route('home') }}#modules" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Modules</a>
                        <a href="{{ route('about') }}" class="text-indigo-600 dark:text-indigo-400 font-medium">About Us</a>
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Log in</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Get Started</a>
                                @endif
                            @endauth
                        @endif
                    </div>

                    <!-- Mobile Menu Button -->
                    <div class="md:hidden">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Mobile Menu -->
                <div x-show="mobileMenuOpen" x-transition class="md:hidden pb-4">
                    <div class="flex flex-col space-y-4">
                        <a href="{{ route('home') }}#features" @click="mobileMenuOpen = false" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Features</a>
                        <a href="{{ route('home') }}#how-it-works" @click="mobileMenuOpen = false" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">How It Works</a>
                        <a href="{{ route('home') }}#modules" @click="mobileMenuOpen = false" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Modules</a>
                        <a href="{{ route('about') }}" class="text-indigo-600 dark:text-indigo-400 font-medium">About Us</a>
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-center">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Log in</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-center">Get Started</a>
                                @endif
                            @endauth
                        @endif
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="pt-32 pb-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-indigo-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
            <div class="max-w-7xl mx-auto text-center">
                <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                    About <span class="gradient-text">MyBooks</span>
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                    We're on a mission to simplify accounting and bookkeeping for businesses of all sizes.
                </p>
            </div>
        </section>

        <!-- Our Story Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Our Story</h2>
                        <div class="space-y-4 text-gray-600 dark:text-gray-300">
                            <p>
                                MyBooks was born out of a simple observation: small and medium businesses struggle with complex, expensive accounting software that doesn't fit their needs.
                            </p>
                            <p>
                                We set out to create a solution that combines powerful features with an intuitive interface, making professional-grade accounting accessible to everyone.
                            </p>
                            <p>
                                Today, MyBooks helps thousands of businesses manage their finances effortlessly, from invoicing and inventory to payroll and financial reporting.
                            </p>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/30 dark:to-purple-900/30 rounded-2xl p-8">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="text-center p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm">
                                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">10K+</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Active Users</div>
                            </div>
                            <div class="text-center p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm">
                                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">2</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Countries</div>
                            </div>
                            <div class="text-center p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm">
                                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">Over 10k</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Invoices Created</div>
                            </div>
                            <div class="text-center p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm">
                                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">99.9%</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Uptime</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Our Mission Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Our Mission & Values</h2>
                    <p class="text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                        We believe every business deserves access to powerful financial tools without the complexity.
                    </p>
                </div>
                <div class="grid md:grid-cols-3 gap-8">
                    <div class="bg-white dark:bg-gray-900 p-8 rounded-2xl shadow-sm">
                        <div class="w-14 h-14 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Simplicity</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            We believe powerful software doesn't have to be complicated. Our intuitive design makes complex tasks simple.
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-900 p-8 rounded-2xl shadow-sm">
                        <div class="w-14 h-14 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Security</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Your financial data deserves the highest protection. We employ enterprise-grade security measures to keep your data safe.
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-900 p-8 rounded-2xl shadow-sm">
                        <div class="w-14 h-14 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Customer Focus</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            We listen to our users and continuously improve based on your feedback. Your success is our success.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why Choose Us Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Why Choose MyBooks?</h2>
                </div>
                <div class="grid md:grid-cols-2 gap-8">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1">All-in-One Solution</h3>
                            <p class="text-gray-600 dark:text-gray-400">Everything you need to manage your business finances in one place.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Easy to Use</h3>
                            <p class="text-gray-600 dark:text-gray-400">Intuitive interface that requires no accounting expertise.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Affordable Pricing</h3>
                            <p class="text-gray-600 dark:text-gray-400">Professional features at a fraction of the cost of enterprise solutions.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Excellent Support</h3>
                            <p class="text-gray-600 dark:text-gray-400">Dedicated customer support team ready to help you succeed.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-indigo-600 to-purple-600">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-3xl lg:text-4xl font-bold text-white mb-6">
                    Ready to Transform Your Business?
                </h2>
                <p class="text-indigo-100 text-lg mb-8">
                    Join thousands of businesses that trust MyBooks for their accounting needs.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="px-8 py-4 bg-white text-indigo-600 rounded-lg hover:bg-gray-100 transition font-semibold shadow-lg">
                            Get Started Free
                        </a>
                    @endif
                    <a href="{{ route('home') }}#features" class="px-8 py-4 bg-transparent text-white border-2 border-white rounded-lg hover:bg-white/10 transition font-semibold">
                        Explore Features
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-300 py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-4 gap-8 mb-8">
                    <div>
                        <h3 class="text-white font-bold text-xl mb-4">MyBooks</h3>
                        <p class="text-gray-400 text-sm">Complete accounting and bookkeeping solution for modern businesses.</p>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Product</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="{{ route('home') }}#features" class="hover:text-white transition">Features</a></li>
                            <li><a href="{{ route('home') }}#modules" class="hover:text-white transition">Modules</a></li>
                            <li><a href="{{ route('home') }}#how-it-works" class="hover:text-white transition">How It Works</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Company</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="{{ route('about') }}" class="text-white font-medium">About Us</a></li>
                            <li><a href="{{ route('contact') }}" class="hover:text-white transition">Contact</a></li>
                            <li><a href="{{ route('support') }}" class="hover:text-white transition">Support</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Legal</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="{{ route('privacy-policy') }}" class="hover:text-white transition">Privacy Policy</a></li>
                            <li><a href="{{ route('terms-of-service') }}" class="hover:text-white transition">Terms of Service</a></li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-800 pt-8 text-center text-sm text-gray-400">
                    <p>&copy; {{ date('Y') }} MyBooks. All rights reserved.</p>
                </div>
            </div>
        </footer>

    </body>
</html>
