<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Privacy Policy - {{ config('app.name', 'MyBooks') }}</title>
        <meta name="description" content="Privacy Policy for MyBooks - Learn how we collect, use, store, and protect your personal and business data.">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .gradient-text { color: #1F4E79; }
        .dark .gradient-text { color: #8AA9CB; }
        </style>
    </head>
    <body class="bg-white dark:bg-gray-900 antialiased" x-data="{ mobileMenuOpen: false }">
        
        @include('partials.public-nav', ['active' => 'privacy-policy'])

        <!-- Hero Section -->
        <section class="pt-32 pb-12 px-4 sm:px-6 lg:px-8 bg-brand-50 dark:bg-gray-900">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                    Privacy <span class="gradient-text">Policy</span>
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
                            <p>MyBooks is an accounting and financial management solution provided through the website my-books.cloud ("MyBooks"). We are committed to protecting the privacy, confidentiality, and integrity of all information entrusted to us by our users.</p>
                            <p>This Privacy Policy explains how we collect, use, store, disclose, and protect personal and business data when you use MyBooks.</p>
                            <p class="font-medium">By accessing or using MyBooks, you agree to the practices described in this Policy.</p>
                        </div>
                    </div>

                    <!-- Section 2 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">2</span>
                            Information We Collect
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-6 pl-11">
                            <p>We collect only information necessary to deliver our services efficiently and securely.</p>
                            
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-6">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-3">a. User Information</h3>
                                <ul class="space-y-2">
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Name</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Email address</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Phone number</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Login credentials</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Organization or business details</li>
                                </ul>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-6">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-3">b. Accounting and Business Data</h3>
                                <ul class="space-y-2">
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Financial records (e.g., invoices, expenses, payments)</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Customer and vendor information</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Employee payroll or accounting-related data</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Reports generated within the system</li>
                                </ul>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-6">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-3">c. Technical Information</h3>
                                <ul class="space-y-2">
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>IP address</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Device and browser type</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Log files and usage activity</li>
                                    <li class="flex items-center"><svg class="w-5 h-5 text-brand-500 mr-2 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Date and time of access</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">3</span>
                            How We Use Information
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We use collected data strictly for legitimate business and operational purposes, including to:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Provide and operate the MyBooks platform</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Process accounting and financial transactions</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Secure user accounts and prevent fraud</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Improve system performance and features</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Communicate system updates, support responses, and service notices</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Meet legal, regulatory, and audit obligations</li>
                            </ul>
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mt-4">
                                <p class="text-red-700 dark:text-red-400 font-medium">We do not sell, rent, or trade user data to third parties.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">4</span>
                            Data Privacy and Confidentiality
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>All user and accounting data are treated as <strong>confidential</strong>.</p>
                            <p>We implement appropriate technical, administrative, and organizational safeguards to protect data against:</p>
                            <div class="grid md:grid-cols-3 gap-4 mt-4">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 text-center">
                                    <svg class="w-8 h-8 text-red-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <p class="font-medium text-gray-900 dark:text-white">Unauthorized access</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 text-center">
                                    <svg class="w-8 h-8 text-orange-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <p class="font-medium text-gray-900 dark:text-white">Loss or misuse</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 text-center">
                                    <svg class="w-8 h-8 text-yellow-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <p class="font-medium text-gray-900 dark:text-white">Alteration or destruction</p>
                                </div>
                            </div>
                            <p class="mt-4">Access to user data is strictly limited to authorized personnel on a need-to-know basis.</p>
                        </div>
                    </div>

                    <!-- Section 5 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">5</span>
                            Employee Conduct and Data Handling
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>MyBooks employees, contractors, and service providers are bound by strict confidentiality and data protection obligations.</p>
                            <p>Employee responsibilities include:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Using data only for approved business purposes</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Maintaining confidentiality at all times</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Complying with internal security policies</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Reporting any suspected data breach immediately</li>
                            </ul>
                            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mt-4">
                                <p class="text-amber-700 dark:text-amber-400">Any breach of these obligations may result in disciplinary action, including termination and legal consequences.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section 6 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">6</span>
                            Data Sharing and Third Parties
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We may share limited data only when necessary:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>With trusted service providers supporting hosting, security, or system maintenance</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>To comply with legal requirements, court orders, or regulatory authorities</li>
                            </ul>
                            <p class="mt-4">All third parties are required to follow data protection standards consistent with this Policy.</p>

                            <h3 id="sub-processors" class="mt-6 text-lg font-semibold text-gray-900 dark:text-white">Sub-processors</h3>
                            <p>These companies process personal data on our behalf:</p>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-gray-200 dark:border-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-gray-900 dark:text-white">Sub-processor</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-900 dark:text-white">What for</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-900 dark:text-white">Where</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach (config('mybooks.sub_processors', []) as $processor)
                                            <tr>
                                                <td class="px-3 py-2">{{ $processor['name'] }}</td>
                                                <td class="px-3 py-2">{{ $processor['purpose'] }}</td>
                                                <td class="px-3 py-2">{{ $processor['location'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Section 7 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">7</span>
                            Data Retention
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We retain user data only for as long as:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>The account remains active, or</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Required to meet legal, regulatory, or accounting obligations</li>
                            </ul>
                            <p class="mt-4">Data may be securely deleted or anonymized once it is no longer required.</p>
                            <p id="closing-a-business">The owner of a business can close it from Settings &rarr; Company Profile &rarr; Close organisation. Its data is erased {{ \App\Models\Tenant::CLOSURE_GRACE_DAYS }} days later unless the owner cancels, and copies in backups are removed as those backups expire ({{ config('mybooks.backup.keep_days') }} days). We keep a record that the request was made and carried out.</p>
                        </div>
                    </div>

                    <!-- Section 8 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">8</span>
                            User Rights
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>Users have the right to:</p>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 flex items-start">
                                    <svg class="w-6 h-6 text-brand-500 mr-3 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Access</p>
                                        <p class="text-sm">Access their personal data</p>
                                    </div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 flex items-start">
                                    <svg class="w-6 h-6 text-brand-500 mr-3 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Correction</p>
                                        <p class="text-sm">Request correction of inaccurate information</p>
                                    </div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 flex items-start">
                                    <svg class="w-6 h-6 text-brand-500 mr-3 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Deletion</p>
                                        <p class="text-sm">Request deletion of data, subject to legal requirements</p>
                                    </div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 flex items-start">
                                    <svg class="w-6 h-6 text-brand-500 mr-3 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Withdraw Consent</p>
                                        <p class="text-sm">Withdraw consent where applicable</p>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-4">Requests can be made using the contact details below.</p>
                        </div>
                    </div>

                    <!-- Section 9 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">9</span>
                            Cookies and System Monitoring
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>MyBooks uses cookies and similar technologies to:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Maintain secure sessions</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Improve user experience</li>
                                <li class="flex items-start"><svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5 flex-shrink-0 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Monitor system performance</li>
                            </ul>
                            <p class="mt-4">Users may control cookies through their browser settings.</p>
                        </div>
                    </div>

                    <!-- Section 10 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">10</span>
                            Complaints and Concerns
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We take privacy concerns seriously.</p>
                            <p>If you have a complaint, concern, or question about data protection or this Privacy Policy, please contact us at:</p>
                            <div class="bg-brand-50 dark:bg-brand-900/20 rounded-xl p-6 mt-4">
                                <p class="mb-2"><strong class="text-gray-900 dark:text-white">Email:</strong> <a href="mailto:support@my-books.cloud" class="text-brand-600 dark:text-brand-300 hover:underline">support@my-books.cloud</a></p>
                                <p><strong class="text-gray-900 dark:text-white">Subject Line:</strong> Privacy Complaint or Data Protection Request</p>
                            </div>
                            <p class="mt-4">All complaints will be reviewed promptly and handled professionally.</p>
                        </div>
                    </div>

                    <!-- Section 11 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">11</span>
                            Changes to This Policy
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>We may update this Privacy Policy from time to time to reflect system improvements, legal requirements, or operational changes.</p>
                            <p>Updated versions will be published on my-books.cloud, and continued use of the platform constitutes acceptance of the revised Policy.</p>
                        </div>
                    </div>

                    <!-- Section 12 -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center mr-3 text-brand-600 dark:text-brand-300 text-sm font-bold">12</span>
                            Contact Information
                        </h2>
                        <div class="text-gray-600 dark:text-gray-300 space-y-4 pl-11">
                            <p>For general inquiries about privacy or data protection:</p>
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
                            This Privacy Policy is designed to promote trust, accountability, and compliance while enabling MyBooks to deliver a secure and reliable accounting solution.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        @include('partials.public-footer', ['active' => 'privacy-policy'])

    </body>
</html>
