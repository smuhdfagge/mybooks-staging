<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Reports') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <!-- Financial Reports -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Financial Reports</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Profit & Loss -->
                    <a href="{{ route('reports.profit-loss') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Profit & Loss</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Income vs expenses overview</p>
                            </div>
                        </div>
                    </a>

                    <!-- Balance Sheet -->
                    <a href="{{ route('reports.balance-sheet') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Balance Sheet</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Assets and liabilities</p>
                            </div>
                        </div>
                    </a>

                    <!-- Cash Flow -->
                    <a href="{{ route('reports.cash-flow') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-cyan-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Cash Flow</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Money in and out</p>
                            </div>
                        </div>
                    </a>

                    <!-- Trial Balance -->
                    <a href="{{ route('reports.trial-balance') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Trial Balance</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Debits and credits summary</p>
                            </div>
                        </div>
                    </a>

                    <!-- General Ledger -->
                    <a href="{{ route('reports.general-ledger') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">General Ledger</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">All account transactions</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Comparative Reports -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Comparative Reports</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Period-over-period comparisons to analyze trends and changes.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Comparative Profit & Loss -->
                    <a href="{{ route('reports.comparative.profit-loss') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gradient-to-r from-green-500 to-emerald-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Comparative P&L</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Compare income & expenses over periods</p>
                            </div>
                        </div>
                    </a>

                    <!-- Comparative Balance Sheet -->
                    <a href="{{ route('reports.comparative.balance-sheet') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Comparative Balance Sheet</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Compare assets & liabilities over periods</p>
                            </div>
                        </div>
                    </a>

                    <!-- Comparative Cash Flow -->
                    <a href="{{ route('reports.comparative.cash-flow') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gradient-to-r from-cyan-500 to-teal-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Comparative Cash Flow</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Compare cash flows over periods</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Tax Reports -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tax Reports</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">VAT/GST returns and tax liability tracking.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- VAT/GST Return -->
                    <a href="{{ route('reports.vat-gst-return') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gradient-to-r from-rose-500 to-pink-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">VAT/GST Return</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Input & output tax summary</p>
                            </div>
                        </div>
                    </a>

                    <!-- Tax Liability -->
                    <a href="{{ route('reports.tax-liability') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gradient-to-r from-amber-500 to-orange-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Tax Liability</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Period-by-period tax obligations</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Receivables & Payables -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Receivables & Payables</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Accounts Receivable -->
                    <a href="{{ route('reports.accounts-receivable') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-emerald-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Accounts Receivable</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Money owed by customers</p>
                            </div>
                        </div>
                    </a>

                    <!-- Accounts Payable -->
                    <a href="{{ route('reports.accounts-payable') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Accounts Payable</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Money owed to vendors</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Sales & Purchases Reports -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sales & Purchases</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Sales by Customer -->
                    <a href="{{ route('reports.sales-by-customer') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Sales by Customer</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Customer sales breakdown</p>
                            </div>
                        </div>
                    </a>

                    <!-- Sales by Item -->
                    <a href="{{ route('reports.sales-by-item') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-violet-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Sales by Item</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Product sales breakdown</p>
                            </div>
                        </div>
                    </a>

                    <!-- Purchases by Vendor -->
                    <a href="{{ route('reports.purchase-by-vendor') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-orange-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Purchases by Vendor</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Vendor purchases breakdown</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Customer Statements -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Customer Statements</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Customer Statement -->
                    <a href="{{ route('reports.customer-statement') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-teal-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Customer Statement</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Activity and balances by customer</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Inventory Reports -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Inventory Reports</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Inventory Summary -->
                    <a href="{{ route('reports.inventory-summary') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-amber-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Inventory Summary</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Stock levels and values</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Payroll Reports -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payroll Reports</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Track employee compensation, deductions, and departmental payroll costs.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Payroll Summary -->
                    <a href="{{ route('reports.payroll-summary') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Payroll Summary</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Salary and deductions overview</p>
                            </div>
                        </div>
                    </a>

                    <!-- Payroll by Department -->
                    <a href="{{ route('reports.payroll-by-department') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Payroll by Department</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Departmental payroll costs</p>
                            </div>
                        </div>
                    </a>

                    <!-- Employee Earnings -->
                    <a href="{{ route('reports.employee-earnings') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-teal-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Employee Earnings</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Detailed earnings breakdown</p>
                            </div>
                        </div>
                    </a>

                    <!-- Payroll Register -->
                    <a href="{{ route('reports.payroll-register') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-violet-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Payroll Register</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Monthly payroll register with all components</p>
                            </div>
                        </div>
                    </a>

                    <!-- YTD Earnings -->
                    <a href="{{ route('reports.ytd-earnings') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-amber-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Year-to-Date Earnings</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Cumulative YTD earnings per employee</p>
                            </div>
                        </div>
                    </a>

                    <!-- Tax Liability -->
                    <a href="{{ route('reports.tax-liability-payroll') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Tax Liability</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Payroll tax summary and breakdown</p>
                            </div>
                        </div>
                    </a>

                    <!-- Employer Contributions -->
                    <a href="{{ route('reports.employer-contributions') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-cyan-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Employer Contributions</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Employer-side costs and contributions</p>
                            </div>
                        </div>
                    </a>

                    <!-- Bank Disbursement -->
                    <a href="{{ route('reports.bank-disbursement') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-emerald-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Bank Disbursement</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Payment list for bank transfers</p>
                            </div>
                        </div>
                    </a>

                    <!-- Salary Revision History -->
                    <a href="{{ route('reports.salary-revision-history') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-rose-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Salary Revision History</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Salary structure changes over time</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Custom Report Builder -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Custom Report Builder</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Create your own reports with flexible data sources, filters, grouping, and calculations.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Custom Report Builder -->
                    <a href="{{ route('reports.custom.index') }}" class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Build Custom Report</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Create & manage custom reports</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
