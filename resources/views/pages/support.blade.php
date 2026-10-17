<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Support - {{ config('app.name', 'MyBooks') }}</title>
        <meta name="description" content="Get help with MyBooks. Access our knowledge base, tutorials, and support resources.">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .gradient-text { color: #1F4E79; }
        .dark .gradient-text { color: #8AA9CB; }
        </style>
    </head>
    <body class="bg-white dark:bg-gray-900 antialiased" x-data="{ mobileMenuOpen: false }">
        
        @include('partials.public-nav', ['active' => 'support'])

        <!-- Hero Section -->
        <section class="pt-32 pb-12 px-4 sm:px-6 lg:px-8 bg-brand-50 dark:bg-gray-900">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                    How Can We <span class="gradient-text">Help?</span>
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-8">
                    Ask us about any part of MyBooks by email or phone. These are the things people ask about most.
                </p>
            </div>
        </section>

        <!-- Knowledge Base Section -->
        <section id="getting-started" class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">What we can help with</h2>
                    <p class="text-gray-600 dark:text-gray-300">Contact us about any of these, or anything else.</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Category 1: Account & Setup -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Account & Setup</h3>
                        </div>
                        <ul class="space-y-3">
                            <li class="text-gray-600 dark:text-gray-400">Creating your account</li>
                            <li class="text-gray-600 dark:text-gray-400">Setting up your company profile</li>
                            <li class="text-gray-600 dark:text-gray-400">Managing user permissions</li>
                            <li class="text-gray-600 dark:text-gray-400">Two-factor authentication</li>
                        </ul>
                    </div>

                    <!-- Category 2: Invoicing -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Invoicing</h3>
                        </div>
                        <ul class="space-y-3">
                            <li class="text-gray-600 dark:text-gray-400">Creating your first invoice</li>
                            <li class="text-gray-600 dark:text-gray-400">Customizing invoice templates</li>
                            <li class="text-gray-600 dark:text-gray-400">Setting up recurring invoices</li>
                            <li class="text-gray-600 dark:text-gray-400">Sending invoice reminders</li>
                        </ul>
                    </div>

                    <!-- Category 3: Expenses -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Expenses & Bills</h3>
                        </div>
                        <ul class="space-y-3">
                            <li class="text-gray-600 dark:text-gray-400">Recording expenses</li>
                            <li class="text-gray-600 dark:text-gray-400">Managing vendor bills</li>
                            <li class="text-gray-600 dark:text-gray-400">Recurring expenses setup</li>
                            <li class="text-gray-600 dark:text-gray-400">Expense categories</li>
                        </ul>
                    </div>

                    <!-- Category 4: Inventory -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Inventory</h3>
                        </div>
                        <ul class="space-y-3">
                            <li class="text-gray-600 dark:text-gray-400">Adding products and services</li>
                            <li class="text-gray-600 dark:text-gray-400">Stock tracking</li>
                            <li class="text-gray-600 dark:text-gray-400">Low stock alerts</li>
                            <li class="text-gray-600 dark:text-gray-400">Inventory reports</li>
                        </ul>
                    </div>

                    <!-- Category 5: Payroll -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Payroll</h3>
                        </div>
                        <ul class="space-y-3">
                            <li class="text-gray-600 dark:text-gray-400">Setting up employees</li>
                            <li class="text-gray-600 dark:text-gray-400">Running payroll</li>
                            <li class="text-gray-600 dark:text-gray-400">Tax deductions</li>
                            <li class="text-gray-600 dark:text-gray-400">Payslip generation</li>
                        </ul>
                    </div>

                    <!-- Category 6: Reports -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Reports</h3>
                        </div>
                        <ul class="space-y-3">
                            <li class="text-gray-600 dark:text-gray-400">Profit & Loss reports</li>
                            <li class="text-gray-600 dark:text-gray-400">Balance sheet</li>
                            <li class="text-gray-600 dark:text-gray-400">Cash flow statements</li>
                            <li class="text-gray-600 dark:text-gray-400">Custom reports</li>
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
                    <p class="text-gray-600 dark:text-gray-300">Email or call us</p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Email Support -->
                    <div class="text-center p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                        <div class="w-16 h-16 bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Email Support</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Get help via email within 24 hours</p>
                        <a href="mailto:support@my-books.cloud" class="text-brand-600 dark:text-brand-300 font-medium hover:underline">support@my-books.cloud</a>
                    </div>

                    <!-- Phone Support -->
                    <div class="text-center p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                        <div class="w-16 h-16 bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Phone Support</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Mon-Fri, 8am to 5pm</p>
                        <a href="tel:+2348023491938" class="text-brand-600 dark:text-brand-300 font-medium hover:underline">+2348023491938</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-brand-900">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-3xl font-bold text-white mb-4">Have a question?</h2>
                <p class="text-brand-100 mb-8">Send us a message and we will get back to you.</p>
                <a href="{{ route('contact') }}" class="inline-block px-8 py-4 bg-white text-brand-600 font-semibold rounded-lg hover:bg-gray-100 transition shadow-lg">
                    Contact Us
                </a>
            </div>
        </section>

        @include('partials.public-footer', ['active' => 'support'])

    </body>
</html>
