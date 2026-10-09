<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Terms of Service - {{ config('app.name', 'MyBooks') }}</title>
        <meta name="description" content="Terms of Service for MyBooks - Read the terms and conditions governing your use of our accounting platform.">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .gradient-text { color: #1F4E79; }
        .dark .gradient-text { color: #8AA9CB; }
        </style>
    </head>
    <body class="bg-white dark:bg-gray-900 antialiased" x-data="{ mobileMenuOpen: false }">
        
        @include('partials.public-nav', ['active' => 'terms-of-service'])

        <!-- Hero Section -->
        <section class="pt-32 pb-12 px-4 sm:px-6 lg:px-8 bg-brand-50 dark:bg-gray-900">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                    Terms of <span class="gradient-text">Service</span>
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    <strong>MyBooks</strong> (my-books.cloud)<br>
                    <span class="text-sm">Effective Date: 22 December, 2025</span>
                </p>
            </div>
        </section>

        <!-- Content Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900">
            <div class="max-w-4xl mx-auto">
                <div class="prose prose-lg dark:prose-invert max-w-none">
                    
                    <!-- Section 1 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">1</span>
                            Introduction
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>Welcome to MyBooks, an online accounting and financial management solution accessible at my-books.cloud ("MyBooks", "we", "our", or "us").</p>
                            <p>These Terms of Service ("Terms") govern your access to and use of the MyBooks platform. By creating an account or using the service, you agree to comply with these Terms. If you do not agree, you must not use MyBooks.</p>
                        </div>
                    </div>

                    <!-- Section 2 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">2</span>
                            Scope of Service
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>MyBooks provides tools for accounting, financial record keeping, reporting, and related business management functions.</p>
                            <p>We reserve the right to update, modify, suspend, or discontinue any part of the service to improve performance, security, or compliance.</p>
                        </div>
                    </div>

                    <!-- Section 3 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">3</span>
                            User Eligibility and Responsibilities
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>By using MyBooks, you confirm that:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>You are authorized to act on behalf of yourself or your organization</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Information you provide is accurate and up to date</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>You will use the platform only for lawful business purposes</li>
                            </ul>
                            
                            <p class="mt-6">You are responsible for:</p>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-6 mt-2">
                                <ul class="space-y-3">
                                    <li class="flex items-start"><svg class="w-5 h-5 text-orange-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>Maintaining the confidentiality of your login credentials</li>
                                    <li class="flex items-start"><svg class="w-5 h-5 text-orange-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>All activities conducted under your account</li>
                                    <li class="flex items-start"><svg class="w-5 h-5 text-orange-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>Ensuring compliance with applicable accounting, tax, and data protection laws</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">4</span>
                            Data Use and Ownership
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                <p class="text-green-700 dark:text-green-400 font-medium">All data entered into MyBooks remains the property of the user or their organization.</p>
                            </div>
                            
                            <p class="mt-4">By using the platform, you grant MyBooks permission to:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Store and process data to deliver the service</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Generate reports and system outputs requested by you</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Perform backups, security checks, and system maintenance</li>
                            </ul>
                            
                            <p class="mt-4 font-medium text-gray-900 dark:text-white">MyBooks does not claim ownership of your financial or business data.</p>
                        </div>
                    </div>

                    <!-- Section 5 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">5</span>
                            Privacy and Data Protection
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We are committed to protecting user data.</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-700 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Personal and accounting data are handled confidentially</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-700 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Access to data is restricted to authorized personnel only</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-700 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Reasonable technical and organizational measures are used to secure data</li>
                            </ul>
                            <p class="mt-4">Detailed data practices are outlined in our <a href="{{ route('privacy-policy') }}" class="text-brand-600 dark:text-brand-300 hover:underline font-medium">Privacy Policy</a>, which forms part of these Terms.</p>
                        </div>
                    </div>

                    <!-- Section 6 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">6</span>
                            Employee Conduct and Access Control
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>MyBooks employees and contractors are bound by confidentiality and data protection obligations.</p>
                            <p>Employee access to user data is:</p>
                            <div class="grid md:grid-cols-3 gap-4 mt-4">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 text-center">
                                    <svg class="w-8 h-8 text-brand-500 mx-auto mb-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">Limited to legitimate purposes</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 text-center">
                                    <svg class="w-8 h-8 text-brand-500 mx-auto mb-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">Logged and monitored</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 text-center">
                                    <svg class="w-8 h-8 text-brand-500 mx-auto mb-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">Governed by internal policies</p>
                                </div>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mt-4">
                                <p class="text-red-700 dark:text-red-300 font-medium">Unauthorized use or disclosure of user data by employees is strictly prohibited.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section 7 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">7</span>
                            Acceptable Use
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>You agree not to:</p>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-6">
                                <ul class="space-y-3">
                                    <li class="flex items-start"><svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>Use MyBooks for illegal or fraudulent activities</li>
                                    <li class="flex items-start"><svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>Upload malicious code or attempt to breach system security</li>
                                    <li class="flex items-start"><svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>Access accounts or data without authorization</li>
                                    <li class="flex items-start"><svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>Misuse the platform in a way that disrupts service availability</li>
                                </ul>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mt-4">
                                <p class="text-amber-700 dark:text-amber-400">Violation of acceptable use rules may result in account suspension or termination.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section 8 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">8</span>
                            Service Availability and Limitations
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>MyBooks is provided on an <strong>"as-is"</strong> and <strong>"as-available"</strong> basis.</p>
                            <p>While we strive for reliability:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>We do not guarantee uninterrupted or error-free service</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Scheduled maintenance or unforeseen outages may occur</li>
                            </ul>
                            <p class="mt-4">MyBooks is not responsible for business decisions made based on system outputs.</p>
                        </div>
                    </div>

                    <!-- Section 9 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">9</span>
                            Fees and Payments
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>Where paid plans or services apply:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Fees are disclosed before subscription</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Payments are due as agreed</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Failure to pay may result in restricted access</li>
                            </ul>
                            <p class="mt-4 font-medium">All fees are non-refundable unless stated otherwise.</p>
                        </div>
                    </div>

                    <!-- Section 10 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">10</span>
                            Complaints and Support
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We are committed to resolving issues fairly and promptly.</p>
                            <p>If you have a complaint or concern regarding:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Data protection</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Service performance</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Employee conduct</li>
                            </ul>
                            <div class="bg-brand-50 dark:bg-brand-900/20 rounded-xl p-6 mt-4">
                                <p class="mb-2"><strong class="text-gray-900 dark:text-white">Email:</strong> <a href="mailto:support@my-books.cloud" class="text-brand-600 dark:text-brand-300 hover:underline">support@my-books.cloud</a></p>
                                <p><strong class="text-gray-900 dark:text-white">Subject:</strong> Complaint or Service Issue</p>
                            </div>
                            <p class="mt-4">All complaints will be reviewed and addressed in a professional manner.</p>
                        </div>
                    </div>

                    <!-- Section 11 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">11</span>
                            Suspension and Termination
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We may suspend or terminate access if:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>These Terms are violated</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>There is suspected misuse or security risk</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>Required by law or regulatory authorities</li>
                            </ul>
                            <p class="mt-4">Users may terminate their account at any time, subject to outstanding obligations.</p>
                        </div>
                    </div>

                    <!-- Section 12 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">12</span>
                            Limitation of Liability
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>To the maximum extent permitted by law:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>MyBooks is not liable for indirect, incidental, or consequential losses</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Liability is limited to the fees paid for the service, where applicable</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section 13 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">13</span>
                            Changes to These Terms
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We may update these Terms periodically.</p>
                            <p>Updated versions will be published on my-books.cloud. Continued use of the platform indicates acceptance of the revised Terms.</p>
                        </div>
                    </div>

                    <!-- Section 14 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">14</span>
                            Governing Law
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>These Terms are governed by applicable laws and regulations in the jurisdiction where MyBooks operates, without prejudice to mandatory consumer protection laws.</p>
                        </div>
                    </div>

                    <!-- Section 15 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">15</span>
                            Contact Information
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>For questions about these Terms:</p>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-6">
                                <h3 class="font-bold text-gray-900 dark:text-white text-lg mb-3">MyBooks</h3>
                                <p class="mb-2"><strong>Website:</strong> <a href="https://my-books.cloud" class="text-brand-600 dark:text-brand-300 hover:underline">https://my-books.cloud</a></p>
                                <p><strong>Email:</strong> <a href="mailto:support@my-books.cloud" class="text-brand-600 dark:text-brand-300 hover:underline">support@my-books.cloud</a></p>
                            </div>
                        </div>
                    </div>

                    <!-- Closing Statement -->
                    <div class="bg-brand-50 dark:bg-brand-900/20 rounded-2xl p-8 text-center">
                        <p class="text-gray-700 dark:text-gray-300 italic">
                            These Terms of Service are designed to promote clarity, accountability, and trust while supporting secure and compliant use of the MyBooks accounting platform.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        @include('partials.public-footer', ['active' => 'terms-of-service'])

    </body>
</html>
