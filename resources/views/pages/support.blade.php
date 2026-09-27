<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Support - {{ config('app.name', 'MyBooks') }}</title>
        <meta name="description" content="Get help with MyBooks. Access our knowledge base, tutorials, and support resources.">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .gradient-text { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        </style>
    </head>
    <body class="bg-white dark:bg-gray-900 antialiased" x-data="{ mobileMenuOpen: false }">
        
        @include('partials.public-nav', ['active' => 'support'])

        <!-- Hero Section -->
        <section class="pt-32 pb-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-indigo-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                    How Can We <span class="gradient-text">Help?</span>
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-8">
                    Find answers, learn best practices, and get the support you need to succeed with MyBooks.
                </p>
                
                <!-- Search Box -->
                <div class="max-w-xl mx-auto">
                    <div class="relative">
                        <input type="text" placeholder="Search for help articles..." 
                            class="w-full px-6 py-4 pl-14 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition shadow-lg">
                        <svg class="absolute left-5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Links Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-3 gap-8">
                    
                    <!-- Getting Started -->
                    <a href="#getting-started" class="group bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-900/20 dark:to-indigo-800/20 rounded-2xl p-8 hover:shadow-lg transition">
                        <div class="w-14 h-14 bg-indigo-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Getting Started</h3>
                        <p class="text-gray-600 dark:text-gray-400">New to MyBooks? Learn the basics and set up your account.</p>
                    </a>

                    <!-- Documentation -->
                    <a href="#documentation" class="group bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl p-8 hover:shadow-lg transition">
                        <div class="w-14 h-14 bg-green-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Documentation</h3>
                        <p class="text-gray-600 dark:text-gray-400">Detailed guides and references for all features.</p>
                    </a>

                    <!-- Video Tutorials -->
                    <a href="#tutorials" class="group bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl p-8 hover:shadow-lg transition">
                        <div class="w-14 h-14 bg-purple-600 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Video Tutorials</h3>
                        <p class="text-gray-600 dark:text-gray-400">Watch step-by-step video guides for visual learning.</p>
                    </a>
                </div>
            </div>
        </section>

        <!-- Knowledge Base Section -->
        <section id="getting-started" class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Knowledge Base</h2>
                    <p class="text-gray-600 dark:text-gray-300">Browse our most popular help topics</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Category 1: Account & Setup -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Account & Setup</h3>
                        </div>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Creating your account</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Setting up your company profile</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Managing user permissions</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Two-factor authentication</a></li>
                        </ul>
                    </div>

                    <!-- Category 2: Invoicing -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Invoicing</h3>
                        </div>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Creating your first invoice</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Customizing invoice templates</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Setting up recurring invoices</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Sending invoice reminders</a></li>
                        </ul>
                    </div>

                    <!-- Category 3: Expenses -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Expenses & Bills</h3>
                        </div>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Recording expenses</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Managing vendor bills</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Recurring expenses setup</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Expense categories</a></li>
                        </ul>
                    </div>

                    <!-- Category 4: Inventory -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Inventory</h3>
                        </div>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Adding products and services</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Stock tracking</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Low stock alerts</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Inventory reports</a></li>
                        </ul>
                    </div>

                    <!-- Category 5: Payroll -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Payroll</h3>
                        </div>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Setting up employees</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Running payroll</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Tax deductions</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Payslip generation</a></li>
                        </ul>
                    </div>

                    <!-- Category 6: Reports -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Reports</h3>
                        </div>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Profit & Loss reports</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Balance sheet</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Cash flow statements</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition flex items-center"><span class="mr-2">→</span> Custom reports</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Support Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Still Need Help?</h2>
                    <p class="text-gray-600 dark:text-gray-300">Our support team is ready to assist you</p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Email Support -->
                    <div class="text-center p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                        <div class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Email Support</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Get help via email within 24 hours</p>
                        <a href="mailto:support@my-books.cloud" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">support@my-books.cloud</a>
                    </div>

                    <!-- Live Chat -->
                    <div class="text-center p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                        <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Live Chat</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Chat with our team in real-time</p>
                        <button @click="if(typeof Tawk_API !== 'undefined') Tawk_API.maximize()" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                            <span class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></span>
                            Start Chat Now
                        </button>
                    </div>

                    <!-- Phone Support -->
                    <div class="text-center p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                        <div class="w-16 h-16 bg-purple-100 dark:bg-purple-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Phone Support</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Mon-Fri, 8am to 5pm</p>
                        <a href="tel:+2348023491938" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">+2348023491938</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- System Status Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-8 shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">System Status</h2>
                        <span class="inline-flex items-center px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full text-sm font-medium">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                            All Systems Operational
                        </span>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-800">
                            <span class="text-gray-700 dark:text-gray-300">Web Application</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">Operational</span>
                        </div>
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-800">
                            <span class="text-gray-700 dark:text-gray-300">API Services</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">Operational</span>
                        </div>
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-800">
                            <span class="text-gray-700 dark:text-gray-300">Database</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">Operational</span>
                        </div>
                        <div class="flex items-center justify-between py-3">
                            <span class="text-gray-700 dark:text-gray-300">Email Services</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">Operational</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Last updated: {{ now()->format('F j, Y, g:i a') }} • 
                            <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline">View incident history</a>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-indigo-600 to-purple-600">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-3xl font-bold text-white mb-4">Can't Find What You're Looking For?</h2>
                <p class="text-indigo-100 mb-8">Our support team is just a message away. We're happy to help!</p>
                <a href="{{ route('contact') }}" class="inline-block px-8 py-4 bg-white text-indigo-600 font-semibold rounded-lg hover:bg-gray-100 transition shadow-lg">
                    Contact Us
                </a>
            </div>
        </section>

        @include('partials.public-footer', ['active' => 'support'])

        @include('partials.tawk-to')
    </body>
</html>
