<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'MyBooks') }} - Complete Accounting & Bookkeeping Solution</title>
        <meta name="description" content="Transform your business operations with MyBooks - a comprehensive accounting system for invoicing, inventory, payroll, expenses, and financial reporting.">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            [x-cloak] { display: none !important; }
            .gradient-text { color: #1F4E79; }
        .dark .gradient-text { color: #8AA9CB; }
        </style>
    </head>
    <body class="bg-white dark:bg-gray-900 antialiased" x-data="{ mobileMenuOpen: false }">
        
        @include('partials.public-nav')

        <!-- Hero Section -->
        <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8 bg-brand-50 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div class="space-y-8">
                        <div class="inline-flex items-center px-4 py-2 bg-brand-100 dark:bg-brand-900/30 rounded-full">
                            <span class="text-sm font-semibold text-brand-600 dark:text-brand-300">Complete Accounting Solution</span>
                        </div>
                        <h1 class="text-5xl lg:text-6xl font-bold text-gray-900 dark:text-white leading-tight">
                            Manage Your Business <span class="gradient-text">Finances</span> Effortlessly
                        </h1>
                        <p class="text-xl text-gray-600 dark:text-gray-300">
                            MyBooks is a comprehensive accounting and bookkeeping system designed to streamline your day-to-day operations. From invoicing to payroll, inventory to financial reporting - we've got you covered.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="px-8 py-4 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition font-semibold text-center shadow-lg hover:shadow-xl">
                                    Get Started
                                </a>
                            @endif
                            <a href="#how-it-works" class="px-8 py-4 bg-white dark:bg-gray-800 text-gray-900 dark:text-white border-2 border-gray-300 dark:border-gray-700 rounded-lg hover:border-brand-600 dark:hover:border-brand-400 transition font-semibold text-center">
                                See How It Works
                            </a>
                        </div>
                       
                    </div>
                    <div class="relative">
                        <div class="bg-brand-900 rounded-2xl shadow-2xl p-8">
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-lg">
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-semibold text-gray-600 dark:text-gray-400">Revenue This Month</span>
                                        <span class="text-green-700 text-sm font-semibold">+12.5%</span>
                                    </div>
                                    <div class="text-3xl font-bold text-gray-900 dark:text-white">₦5,239,343</div>
                                    <div class="h-32 flex items-end gap-2 border-b border-gray-200" aria-hidden="true"><div class="flex-1 rounded-t bg-brand-200" style="height: 35%"></div><div class="flex-1 rounded-t bg-brand-200" style="height: 50%"></div><div class="flex-1 rounded-t bg-brand-200" style="height: 42%"></div><div class="flex-1 rounded-t bg-brand-300" style="height: 64%"></div><div class="flex-1 rounded-t bg-brand-300" style="height: 58%"></div><div class="flex-1 rounded-t bg-brand-500" style="height: 78%"></div><div class="flex-1 rounded-t bg-brand-600" style="height: 92%"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Overview -->
        <section id="features" class="py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Everything You Need to Run Your Business</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300">Powerful features designed for modern businesses</p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature Cards -->
                    <div class="group p-6 bg-gray-50 dark:bg-gray-800 rounded-xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700 hover:border-brand-500 dark:hover:border-brand-400">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4 group-hover:scale-110 transition">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Invoicing & Billing</h3>
                        <p class="text-gray-600 dark:text-gray-300">Create professional invoices, track payments, and manage customer billing with ease. Send invoices via email and generate PDFs instantly.</p>
                    </div>

                    <div class="group p-6 bg-gray-50 dark:bg-gray-800 rounded-xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700 hover:border-brand-500 dark:hover:border-brand-400">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4 group-hover:scale-110 transition">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Inventory Management</h3>
                        <p class="text-gray-600 dark:text-gray-300">Track stock levels, manage items and categories, view inventory history, and get low-stock alerts to prevent shortages.</p>
                    </div>

                    <div class="group p-6 bg-gray-50 dark:bg-gray-800 rounded-xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700 hover:border-brand-500 dark:hover:border-brand-400">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4 group-hover:scale-110 transition">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Expense Tracking</h3>
                        <p class="text-gray-600 dark:text-gray-300">Record and categorize expenses, manage vendor bills, and set up recurring expenses for automated tracking.</p>
                    </div>

                    <div class="group p-6 bg-gray-50 dark:bg-gray-800 rounded-xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700 hover:border-brand-500 dark:hover:border-brand-400">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4 group-hover:scale-110 transition">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Payroll Management</h3>
                        <p class="text-gray-600 dark:text-gray-300">Manage employee records, departments, designations, process payroll, and track leave requests all in one place.</p>
                    </div>

                    <div class="group p-6 bg-gray-50 dark:bg-gray-800 rounded-xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700 hover:border-brand-500 dark:hover:border-brand-400">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4 group-hover:scale-110 transition">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Financial Reporting</h3>
                        <p class="text-gray-600 dark:text-gray-300">Generate comprehensive financial reports, track journal entries, manage chart of accounts, and gain insights into your business performance.</p>
                    </div>

                    <div class="group p-6 bg-gray-50 dark:bg-gray-800 rounded-xl hover:shadow-xl transition duration-300 border border-gray-200 dark:border-gray-700 hover:border-brand-500 dark:hover:border-brand-400">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4 group-hover:scale-110 transition">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Sales Management</h3>
                        <p class="text-gray-600 dark:text-gray-300">Create sales orders, receipts, track payments received, manage customers, and convert orders to invoices seamlessly.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section id="how-it-works" class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">How MyBooks Supports Your Daily Operations</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300">From setup to success in 4 simple steps</p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-brand-600 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">1</div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Create Account</h3>
                        <p class="text-gray-600 dark:text-gray-300">Sign up in seconds and set up your company profile with tenant-based multi-company support.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-brand-600 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">2</div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Configure Settings</h3>
                        <p class="text-gray-600 dark:text-gray-300">Set up your chart of accounts, add items, customers, vendors, and employees to get started.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-brand-600 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">3</div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Daily Operations</h3>
                        <p class="text-gray-600 dark:text-gray-300">Create invoices, track expenses, manage inventory, process payroll, and record transactions daily.</p>
                    </div>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-brand-600 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">4</div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Generate Reports</h3>
                        <p class="text-gray-600 dark:text-gray-300">Get real-time insights with comprehensive financial reports and analytics to make informed decisions.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modules Breakdown -->
        <section id="modules" class="py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Comprehensive Business Modules</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300">All the tools you need in one integrated platform</p>
                </div>
                
                <div class="space-y-12">
                    <!-- Sales Module -->
                    <div class="grid lg:grid-cols-2 gap-8 items-center">
                        <div class="space-y-4">
                            <div class="inline-block px-4 py-2 bg-brand-100 dark:bg-brand-900/30 rounded-full">
                                <span class="text-sm font-semibold text-brand-600 dark:text-brand-300">Sales Module</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 dark:text-white">Complete Sales Cycle Management</h3>
                            <p class="text-lg text-gray-600 dark:text-gray-300">Manage your entire sales process from quotes to payment collection.</p>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Customer Management:</strong> <span class="text-gray-600 dark:text-gray-300">Track customer information, purchase history, and outstanding balances</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Sales Orders:</strong> <span class="text-gray-600 dark:text-gray-300">Create, confirm, and convert sales orders to invoices automatically</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Invoicing:</strong> <span class="text-gray-600 dark:text-gray-300">Generate professional invoices, send via email, download PDFs, and track payment status</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Payment Tracking:</strong> <span class="text-gray-600 dark:text-gray-300">Record payments received, manage partial payments, and reconcile accounts</span></div>
                                </li>
                            </ul>
                        </div>
                        <div class="bg-brand-900 rounded-2xl p-8 shadow-2xl">
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 space-y-4">
                                <div class="flex justify-between items-center pb-4 border-b border-gray-200 dark:border-gray-700">
                                    <span class="font-semibold text-gray-900 dark:text-white">Invoice #INV-2024-001</span>
                                    <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full text-sm">Paid</span>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Customer:</span><span class="text-gray-900 dark:text-white font-medium">Muhammad Abdullahi</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Date:</span><span class="text-gray-900 dark:text-white">Dec 13, 2025</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Amount:</span><span class="text-gray-900 dark:text-white font-semibold">₦249,450.00</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Purchases Module -->
                    <div class="grid lg:grid-cols-2 gap-8 items-center">
                        <div class="bg-brand-900 rounded-2xl p-8 shadow-2xl lg:order-1 order-2">
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 space-y-4">
                                <div class="flex justify-between items-center pb-4 border-b border-gray-200 dark:border-gray-700">
                                    <span class="font-semibold text-gray-900 dark:text-white">Bill #BILL-2024-089</span>
                                    <span class="px-3 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 rounded-full text-sm">Pending</span>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Vendor:</span><span class="text-gray-900 dark:text-white font-medium">Onyx Investment Advisory Ltd</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Due Date:</span><span class="text-gray-900 dark:text-white">Dec 20, 2025</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Amount:</span><span class="text-gray-900 dark:text-white font-semibold">₦100,230.00</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4 lg:order-2 order-1">
                            <div class="inline-block px-4 py-2 bg-accent-100 dark:bg-accent-900/30 rounded-full">
                                <span class="text-sm font-semibold text-accent-700 dark:text-accent-300">Purchases Module</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 dark:text-white">Streamlined Purchase Management</h3>
                            <p class="text-lg text-gray-600 dark:text-gray-300">Control your spending and manage vendor relationships effectively.</p>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Vendor Management:</strong> <span class="text-gray-600 dark:text-gray-300">Maintain vendor database with contact info and payment terms</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Bills & Expenses:</strong> <span class="text-gray-600 dark:text-gray-300">Record vendor bills, one-time expenses, and recurring expenses automatically</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Payment Processing:</strong> <span class="text-gray-600 dark:text-gray-300">Track payments made to vendors and reconcile accounts payable</span></div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Inventory Module -->
                    <div class="grid lg:grid-cols-2 gap-8 items-center">
                        <div class="space-y-4">
                            <div class="inline-block px-4 py-2 bg-green-100 dark:bg-green-900/30 rounded-full">
                                <span class="text-sm font-semibold text-green-700 dark:text-green-400">Inventory Module</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 dark:text-white">Real-Time Inventory Control</h3>
                            <p class="text-lg text-gray-600 dark:text-gray-300">Never run out of stock or overstock with intelligent inventory tracking.</p>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Item Management:</strong> <span class="text-gray-600 dark:text-gray-300">Manage products, SKUs, categories, pricing, and tax rates</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Stock Tracking:</strong> <span class="text-gray-600 dark:text-gray-300">Monitor current stock levels, view inventory value, and track movement</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Inventory Adjustments:</strong> <span class="text-gray-600 dark:text-gray-300">Adjust stock levels for damages, returns, or inventory counts with full audit trail</span></div>
                                </li>
                            </ul>
                        </div>
                        <div class="bg-brand-900 rounded-2xl p-8 shadow-2xl">
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 space-y-4">
                                <div class="pb-4 border-b border-gray-200 dark:border-gray-700">
                                    <span class="font-semibold text-gray-900 dark:text-white">Laptop - Dell XPS 15</span>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">SKU:</span><span class="text-gray-900 dark:text-white font-medium">DXPS-15-001</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">In Stock:</span><span class="text-green-700 dark:text-green-400 font-semibold">11 units</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Reorder Level:</span><span class="text-gray-900 dark:text-white">20 units</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Value:</span><span class="text-gray-900 dark:text-white font-semibold">₦10,615,000</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- HR & Payroll Module -->
                    <div class="grid lg:grid-cols-2 gap-8 items-center">
                        <div class="bg-brand-900 rounded-2xl p-8 shadow-2xl lg:order-1 order-2">
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 space-y-4">
                                <div class="pb-4 border-b border-gray-200 dark:border-gray-700">
                                    <span class="font-semibold text-gray-900 dark:text-white">Payroll - December 2025</span>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total Employees:</span><span class="text-gray-900 dark:text-white font-medium">15</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Gross Payroll:</span><span class="text-gray-900 dark:text-white">₦484,500</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Deductions:</span><span class="text-gray-900 dark:text-white">₦85,675</span></div>
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Net Payroll:</span><span class="text-gray-900 dark:text-white font-semibold">₦398,825</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4 lg:order-2 order-1">
                            <div class="inline-block px-4 py-2 bg-orange-100 dark:bg-orange-900/30 rounded-full">
                                <span class="text-sm font-semibold text-orange-700 dark:text-orange-400">HR & Payroll Module</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 dark:text-white">Comprehensive Employee Management</h3>
                            <p class="text-lg text-gray-600 dark:text-gray-300">Manage your workforce and payroll with ease and accuracy.</p>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Employee Records:</strong> <span class="text-gray-600 dark:text-gray-300">Store employee details, departments, designations, and employment history</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Payroll Processing:</strong> <span class="text-gray-600 dark:text-gray-300">Calculate salaries, deductions, bonuses, and generate payslips automatically</span></div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-green-700 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <div><strong class="text-gray-900 dark:text-white">Leave Management:</strong> <span class="text-gray-600 dark:text-gray-300">Track leave types, requests, approvals, and employee leave balances</span></div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Financial Accounting Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Advanced Financial Accounting</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300">Professional-grade accounting features for complete financial control</p>
                </div>
                <div class="grid md:grid-cols-3 gap-8">
                    <div class="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-lg">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Chart of Accounts</h3>
                        <p class="text-gray-600 dark:text-gray-300">Customizable chart of accounts with account types: assets, liabilities, equity, income, and expenses. Track every financial transaction accurately.</p>
                    </div>
                    <div class="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-lg">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Journal Entries</h3>
                        <p class="text-gray-600 dark:text-gray-300">Record manual journal entries with debit and credit entries. Maintain complete audit trail of all financial transactions with date stamps and descriptions.</p>
                    </div>
                    <div class="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-lg">
                        <div class="w-12 h-12 bg-brand-100 dark:bg-brand-900/40 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Financial Reports</h3>
                        <p class="text-gray-600 dark:text-gray-300">Generate profit & loss statements, balance sheets, cash flow reports, aged receivables, aged payables, and custom financial reports on demand.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Trusted by Businesses across Nigeria</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300">See what our customers say about MyBooks</p>
                </div>
                <div class="grid md:grid-cols-3 gap-8">
                    <div class="bg-gray-50 dark:bg-gray-800 p-8 rounded-xl shadow-lg">
                        <div class="flex items-center gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-4 italic">"MyBooks transformed how we manage our finances. The automation features saved us countless hours each month. Highly recommended!"</p>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-brand-600 rounded-full flex items-center justify-center text-white font-semibold">SM</div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Sirajo Muhammad</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">CEO, Dan Yaro Galleria</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 p-8 rounded-xl shadow-lg">
                        <div class="flex items-center gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-4 italic">"The inventory management is outstanding. Real-time tracking and automatic updates make our operations so much smoother."</p>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-accent-700 rounded-full flex items-center justify-center text-white font-semibold">YS</div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Yushau Suleiman</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Chairman, Ando Rice Mill Ltd</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 p-8 rounded-xl shadow-lg">
                        <div class="flex items-center gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-4 italic">"Best value for money. The multi-tenant feature allows us to manage multiple business entities seamlessly. Excellent support team!"</p>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-700 rounded-full flex items-center justify-center text-white font-semibold">JR</div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Jamil Rabiu</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Chairman, El-Jameel Plastics</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-20 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Simple, Transparent Pricing</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300">Choose the perfect plan for your business needs</p>
                </div>
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Starter Plan -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 hover:shadow-2xl transition">
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Starter</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">Perfect for small businesses</p>
                            <div class="mb-2">
                                <span class="text-5xl font-bold text-gray-900 dark:text-white">₦25,000</span>
                                <span class="text-gray-600 dark:text-gray-400">/month</span>
                            </div>
                            <p class="text-sm text-green-700 dark:text-green-400 mb-6">or ₦250,000/year (Save 17%)</p>
                            <a href="{{ route('register', ['plan' => 'starter']) }}" class="block w-full px-6 py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 transition font-semibold">Get Started</a>
                        </div>
                        <ul class="mt-8 space-y-4">
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">Up to 5 users</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">All core features</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">Invoicing & expenses</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">Financial reports</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">Email support</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Professional Plan -->
                    <div class="bg-brand-900 rounded-2xl shadow-2xl p-8 transform md:scale-105 relative">
                        <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-yellow-400 text-gray-900 px-4 py-1 rounded-full text-sm font-semibold">Most Popular</div>
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-white mb-2">Professional</h3>
                            <p class="text-brand-100 mb-6">For growing businesses</p>
                            <div class="mb-2">
                                <span class="text-5xl font-bold text-white">₦50,000</span>
                                <span class="text-brand-100">/month</span>
                            </div>
                            <p class="text-sm text-yellow-300 mb-6">Billed annually at ₦500,000/year</p>
                            <a href="{{ route('register', ['plan' => 'professional']) }}" class="block w-full px-6 py-3 bg-white text-brand-600 rounded-lg hover:bg-gray-100 transition font-semibold">Get Started</a>
                        </div>
                        <ul class="mt-8 space-y-4">
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-yellow-300 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-white">Up to 15 users</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-yellow-300 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-white">All Starter features</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-yellow-300 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-white">HR & Payroll management</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-yellow-300 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-white">Advanced reporting & analytics</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-yellow-300 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-white">Priority support</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Enterprise Plan -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 hover:shadow-2xl transition">
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Enterprise</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">For large organizations</p>
                            <div class="mb-2">
                                <span class="text-5xl font-bold text-gray-900 dark:text-white">₦150,000</span>
                                <span class="text-gray-600 dark:text-gray-400">/month</span>
                            </div>
                            <p class="text-sm text-green-700 dark:text-green-400 mb-6">or ₦1,500,000/year (Save 17%)</p>
                            <a href="{{ route('register', ['plan' => 'enterprise']) }}" class="block w-full px-6 py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 transition font-semibold">Get Started</a>
                        </div>
                        <ul class="mt-8 space-y-4">
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">Up to 50 users</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">All Professional features</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">Multi-branch support</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">Custom integrations & API</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-gray-600 dark:text-gray-300">24/7 phone support</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </section>

        <!-- Trust & Security Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto text-center">
                <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Security & Compliance You Can Trust</h2>
                <p class="text-xl text-gray-600 dark:text-gray-300 mb-12">Your data security is our top priority</p>
                <div class="grid md:grid-cols-4 gap-8">
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">SSL Encrypted</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Bank-level encryption</p>
                    </div>
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">GDPR Compliant</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Data protection</p>
                    </div>
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Daily Backups</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Never lose data</p>
                    </div>
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Role-Based Access</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Control permissions</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-brand-900">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-4xl font-bold text-white mb-4">Ready to Transform Your Business?</h2>
                <p class="text-xl text-brand-100 mb-8">Join thousands of companies using MyBooks to manage their finances</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="px-8 py-4 bg-white text-brand-600 rounded-lg hover:bg-gray-100 transition font-semibold shadow-lg hover:shadow-xl">
                            Start Your Free Trial
                        </a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="px-8 py-4 bg-transparent text-white border-2 border-white rounded-lg hover:bg-white hover:text-brand-600 transition font-semibold">
                            Sign In to Your Account
                        </a>
                    @endif
                </div>
                <p class="text-brand-100 mt-6 text-sm">No credit card required • 14-day free trial • Cancel anytime</p>
                <div class="mt-8">
                    <button id="start-chat-btn" type="button" class="inline-flex items-center px-6 py-3 text-brand-100 hover:text-white border border-brand-300/40 rounded-lg hover:bg-white/10 transition cursor-pointer">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        Have questions? Chat with us
                    </button>
                </div>
            </div>
        </section>

        @include('partials.public-footer')

        @include('partials.tawk-to')
        <script nonce="{{ app('csp-nonce') }}">
            document.getElementById('start-chat-btn').addEventListener('click', function() {
                if (window.Tawk_API) { window.Tawk_API.maximize(); }
            });
        </script>
    </body>
</html>
