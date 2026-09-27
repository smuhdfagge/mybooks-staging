<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Cash Flow Statement') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="cash-flow" 
                    :filters="['start_date' => $startDate, 'end_date' => $endDate]" 
                />
                <a href="{{ route('reports.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Reports
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <form method="GET" action="{{ route('reports.cash-flow') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Net Cash Flow Summary -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Net Cash Flow</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            For the period {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold {{ $netCashFlow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $netCashFlow >= 0 ? '+' : '-' }}{{ number_format(abs($netCashFlow), 2) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ $netCashFlow >= 0 ? 'Positive cash flow' : 'Negative cash flow' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Cash Inflows</dt>
                                <dd class="text-lg font-semibold text-green-600 dark:text-green-400">+{{ number_format($totalInflows, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Cash Outflows</dt>
                                <dd class="text-lg font-semibold text-red-600 dark:text-red-400">-{{ number_format($totalOutflows, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash Reconciliation -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Cash Reconciliation</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Beginning Cash</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($beginningCash ?? 0, 2) }}</p>
                    </div>
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Net Cash Change</p>
                        <p class="text-xl font-bold {{ $netCashFlow >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $netCashFlow >= 0 ? '+' : '-' }}{{ number_format(abs($netCashFlow), 2) }}
                        </p>
                    </div>
                    <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Ending Cash</p>
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($endingCash ?? 0, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash Flow by Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Operating Activities -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Operating Activities</h3>
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-md p-2">
                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Customer Payments</span>
                            <span class="text-green-600 dark:text-green-400">+{{ number_format($paymentsReceived, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Vendor Payments</span>
                            <span class="text-red-600 dark:text-red-400">-{{ number_format($paymentsMade, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Operating Expenses</span>
                            <span class="text-red-600 dark:text-red-400">-{{ number_format($expensesPaid, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Payroll</span>
                            <span class="text-red-600 dark:text-red-400">-{{ number_format($payrollPaid, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-600 pt-3">
                            <div class="flex justify-between">
                                <span class="font-semibold text-gray-900 dark:text-white">Net Operating</span>
                                <span class="font-semibold {{ ($netOperatingCashFlow ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ ($netOperatingCashFlow ?? 0) >= 0 ? '+' : '-' }}{{ number_format(abs($netOperatingCashFlow ?? 0), 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Investing Activities -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Investing Activities</h3>
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-md p-2">
                            <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Asset Purchases</span>
                            <span class="text-red-600 dark:text-red-400">-{{ number_format($fixedAssetPurchases ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Asset Sales</span>
                            <span class="text-green-600 dark:text-green-400">+{{ number_format($fixedAssetSales ?? 0, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-600 pt-3">
                            <div class="flex justify-between">
                                <span class="font-semibold text-gray-900 dark:text-white">Net Investing</span>
                                <span class="font-semibold {{ ($netInvestingCashFlow ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ ($netInvestingCashFlow ?? 0) >= 0 ? '+' : '-' }}{{ number_format(abs($netInvestingCashFlow ?? 0), 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financing Activities -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Financing Activities</h3>
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 rounded-md p-2">
                            <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Borrowings</span>
                            <span class="text-green-600 dark:text-green-400">+{{ number_format($borrowingsReceived ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Loan Repayments</span>
                            <span class="text-red-600 dark:text-red-400">-{{ number_format($loanRepayments ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Capital Contributions</span>
                            <span class="text-green-600 dark:text-green-400">+{{ number_format($capitalContributions ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Owner Drawings</span>
                            <span class="text-red-600 dark:text-red-400">-{{ number_format($drawings ?? 0, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-600 pt-3">
                            <div class="flex justify-between">
                                <span class="font-semibold text-gray-900 dark:text-white">Net Financing</span>
                                <span class="font-semibold {{ ($netFinancingCashFlow ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ ($netFinancingCashFlow ?? 0) >= 0 ? '+' : '-' }}{{ number_format(abs($netFinancingCashFlow ?? 0), 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash Flow Status -->
        @if($netCashFlow >= 0)
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800 dark:text-green-200">Positive Cash Flow</h3>
                        <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                            Your business generated more cash than it spent during this period. This is a healthy sign of financial stability.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Negative Cash Flow</h3>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                            Your business spent more cash than it generated during this period. Consider reviewing expenses or increasing revenue.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Report Info -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <strong>Statement of Cash Flows (Direct Method)</strong> - Report generated for period: <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</span> 
                to <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
            </p>
        </div>
    </div>
</x-app-layout>