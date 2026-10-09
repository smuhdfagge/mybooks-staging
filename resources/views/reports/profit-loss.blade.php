<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Profit & Loss Statement') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="profit-loss" 
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
                <form method="GET" action="{{ route('reports.profit-loss') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Net Profit/Loss Summary -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            For the period {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold {{ $netProfit >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-300' }}">
                            {{ $netProfit >= 0 ? '' : '-' }}{{ number_format(abs($netProfit), 2) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            @if($revenue > 0)
                                {{ number_format(($netProfit / $revenue) * 100, 1) }}% profit margin
                            @else
                                N/A
                            @endif
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Revenue</dt>
                                <dd class="text-lg font-semibold text-green-700 dark:text-green-400">{{ number_format($revenue, 2) }}</dd>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Expenses</dt>
                                <dd class="text-lg font-semibold text-red-600 dark:text-red-300">{{ number_format($totalExpenses, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit & Loss Details - Standard Format -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Income Statement</h3>
                
                <div class="space-y-6">
                    <!-- Revenue Section -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Revenue</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-green-500 rounded-full p-2">
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Sales Revenue</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Income from sales</p>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold text-green-700 dark:text-green-400">{{ number_format($revenue, 2) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Total Revenue</span>
                            <span class="text-sm font-semibold text-green-700 dark:text-green-400">{{ number_format($revenue, 2) }}</span>
                        </div>
                    </div>

                    <!-- Cost of Goods Sold Section -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Cost of Goods Sold</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-amber-500 rounded-full p-2">
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Cost of Goods Sold</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Direct costs of products/services sold</p>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold text-red-600 dark:text-red-300">({{ number_format($costOfGoodsSold, 2) }})</span>
                            </div>
                        </div>
                        <div class="flex justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Total COGS</span>
                            <span class="text-sm font-semibold text-red-600 dark:text-red-300">({{ number_format($costOfGoodsSold, 2) }})</span>
                        </div>
                    </div>

                    <!-- Gross Profit -->
                    <div class="bg-brand-50 dark:bg-brand-900/20 rounded-lg p-4 border border-brand-200 dark:border-brand-800">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-base font-bold text-brand-900 dark:text-brand-100">Gross Profit</span>
                                <p class="text-xs text-brand-600 dark:text-brand-300 mt-1">
                                    @if($revenue > 0)
                                        {{ number_format(($grossProfit / $revenue) * 100, 1) }}% gross margin
                                    @else
                                        N/A
                                    @endif
                                </p>
                            </div>
                            <span class="text-lg font-bold {{ $grossProfit >= 0 ? 'text-brand-600 dark:text-brand-300' : 'text-red-600 dark:text-red-300' }}">
                                {{ $grossProfit >= 0 ? '' : '-' }}{{ number_format(abs($grossProfit), 2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Operating Expenses Section -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Operating Expenses</h4>
                        <div class="space-y-3">
                            <!-- General Operating Expenses -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-orange-500 rounded-full p-2">
                                            <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">General & Administrative</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Rent, utilities, office supplies, etc.</p>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold text-red-600 dark:text-red-300">({{ number_format($operatingExpenses, 2) }})</span>
                                </div>
                            </div>

                            <!-- Payroll -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-accent-500 rounded-full p-2">
                                            <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">Salaries & Wages</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Employee compensation</p>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold text-red-600 dark:text-red-300">({{ number_format($payroll, 2) }})</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Total Operating Expenses</span>
                            <span class="text-sm font-semibold text-red-600 dark:text-red-300">({{ number_format($operatingExpenses + $payroll, 2) }})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit/Loss Calculation - Standard Format -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Profit & Loss Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Revenue</span>
                        <span class="text-sm font-medium text-green-700 dark:text-green-400">{{ number_format($revenue, 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Less: Cost of Goods Sold</span>
                        <span class="text-sm font-medium text-red-600 dark:text-red-300">({{ number_format($costOfGoodsSold, 2) }})</span>
                    </div>
                    <div class="flex justify-between py-2 border-b-2 border-brand-300 dark:border-brand-600 bg-brand-50 dark:bg-brand-900/20 px-3 rounded">
                        <span class="text-sm font-semibold text-brand-900 dark:text-brand-100">Gross Profit</span>
                        <span class="text-sm font-semibold {{ $grossProfit >= 0 ? 'text-brand-600 dark:text-brand-300' : 'text-red-600 dark:text-red-300' }}">{{ $grossProfit >= 0 ? '' : '-' }}{{ number_format(abs($grossProfit), 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Less: Operating Expenses</span>
                        <span class="text-sm font-medium text-red-600 dark:text-red-300">({{ number_format($operatingExpenses, 2) }})</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Less: Salaries & Wages</span>
                        <span class="text-sm font-medium text-red-600 dark:text-red-300">({{ number_format($payroll, 2) }})</span>
                    </div>
                    <div class="flex justify-between py-3 bg-gray-50 dark:bg-gray-700 rounded-lg px-4 mt-4">
                        <span class="text-base font-semibold text-gray-900 dark:text-white">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</span>
                        <span class="text-base font-semibold {{ $netProfit >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-300' }}">
                            {{ $netProfit >= 0 ? '' : '-' }}{{ number_format(abs($netProfit), 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit/Loss Status -->
        @if($netProfit >= 0)
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800 dark:text-green-200">Profitable Period</h3>
                        <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                            Your business made a profit of {{ number_format($netProfit, 2) }} during this period. Keep up the good work!
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
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Loss Period</h3>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                            Your business incurred a loss of {{ number_format(abs($netProfit), 2) }} during this period. Consider reviewing your expenses or increasing revenue.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Report Info -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Report generated for period: <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</span> 
                to <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
            </p>
        </div>
    </div>
</x-app-layout>
