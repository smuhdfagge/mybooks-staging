<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sales Analytics Dashboard') }}
            </h2>
            <div class="mt-2 sm:mt-0">
                <a href="{{ route('dashboard') }}" class="text-sm text-brand-600 dark:text-brand-300 hover:underline">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div x-data="analyticsApp()" x-init="initCharts()" class="space-y-6">
        <!-- Period Selector -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <form method="GET" action="{{ route('analytics.index') }}" class="flex flex-wrap items-center gap-4">
                <div class="flex items-center space-x-2">
                    <label for="period" class="text-sm font-medium text-gray-700 dark:text-gray-300">Period:</label>
                    <select name="period" id="period" @change="$el.form.submit()" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                        <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                        <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                        <option value="this_week" {{ $period === 'this_week' ? 'selected' : '' }}>This Week</option>
                        <option value="last_week" {{ $period === 'last_week' ? 'selected' : '' }}>Last Week</option>
                        <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ $period === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_quarter" {{ $period === 'this_quarter' ? 'selected' : '' }}>This Quarter</option>
                        <option value="last_quarter" {{ $period === 'last_quarter' ? 'selected' : '' }}>Last Quarter</option>
                        <option value="this_year" {{ $period === 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="last_year" {{ $period === 'last_year' ? 'selected' : '' }}>Last Year</option>
                        <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>
                
                <div id="customDateRange" class="{{ $period === 'custom' ? '' : 'hidden' }} flex items-center space-x-2">
                    <input type="date" name="start_date" value="{{ $startDate }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                    <span class="text-gray-500">to</span>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                    <button type="submit" class="px-3 py-1.5 bg-brand-600 text-white text-sm rounded-md hover:bg-brand-700">Apply</button>
                </div>

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</span>
                    <span>to</span>
                    <span class="font-medium">{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
                    @if($rangeCapped ?? false)
                        <span class="block text-xs text-amber-600 dark:text-amber-400">Custom ranges are limited to 24 months, so the start date was moved.</span>
                    @endif
                    <span class="block text-xs">Figures refresh every 10 minutes.</span>
                </div>
            </form>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Revenue -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Revenue</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ number_format($kpis['revenue']['current'], 2) }}
                        </p>
                    </div>
                    <div class="p-3 rounded-full bg-brand-100 dark:bg-brand-900">
                        <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-2 flex items-center text-sm">
                    @if($kpis['revenue']['change'] >= 0)
                        <span class="text-green-700 dark:text-green-400 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                            </svg>
                            {{ $kpis['revenue']['change'] }}%
                        </span>
                    @else
                        <span class="text-red-600 dark:text-red-300 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                            {{ abs($kpis['revenue']['change']) }}%
                        </span>
                    @endif
                    <span class="text-gray-500 dark:text-gray-400 ml-2">vs previous period</span>
                </div>
            </div>

            <!-- Invoices -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Invoices</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ $kpis['invoices']['current'] }}
                        </p>
                    </div>
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <svg class="w-6 h-6 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-2 flex items-center text-sm">
                    @if($kpis['invoices']['change'] >= 0)
                        <span class="text-green-700 dark:text-green-400 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                            </svg>
                            {{ $kpis['invoices']['change'] }}%
                        </span>
                    @else
                        <span class="text-red-600 dark:text-red-300 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                            {{ abs($kpis['invoices']['change']) }}%
                        </span>
                    @endif
                    <span class="text-gray-500 dark:text-gray-400 ml-2">vs previous period</span>
                </div>
            </div>

            <!-- Avg Order Value -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Order Value</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ number_format($kpis['avg_order_value']['current'], 2) }}
                        </p>
                    </div>
                    <div class="p-3 rounded-full bg-accent-100 dark:bg-accent-900/50">
                        <svg class="w-6 h-6 text-accent-700 dark:text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-2 flex items-center text-sm">
                    @if($kpis['avg_order_value']['change'] >= 0)
                        <span class="text-green-700 dark:text-green-400 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                            </svg>
                            {{ $kpis['avg_order_value']['change'] }}%
                        </span>
                    @else
                        <span class="text-red-600 dark:text-red-300 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                            {{ abs($kpis['avg_order_value']['change']) }}%
                        </span>
                    @endif
                    <span class="text-gray-500 dark:text-gray-400 ml-2">vs previous period</span>
                </div>
            </div>

            <!-- New Customers -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">New Customers</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ $kpis['new_customers']['current'] }}
                        </p>
                    </div>
                    <div class="p-3 rounded-full bg-orange-100 dark:bg-orange-900">
                        <svg class="w-6 h-6 text-orange-700 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-2 flex items-center text-sm">
                    @if($kpis['new_customers']['change'] >= 0)
                        <span class="text-green-700 dark:text-green-400 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                            </svg>
                            {{ $kpis['new_customers']['change'] }}%
                        </span>
                    @else
                        <span class="text-red-600 dark:text-red-300 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                            {{ abs($kpis['new_customers']['change']) }}%
                        </span>
                    @endif
                    <span class="text-gray-500 dark:text-gray-400 ml-2">vs previous period</span>
                </div>
            </div>
        </div>

        <!-- Secondary KPIs -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Collection Rate</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $kpis['collection_rate'] }}%</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Outstanding Balance</p>
                <p class="text-lg font-semibold text-yellow-700 dark:text-yellow-400">{{ number_format($kpis['outstanding_balance'], 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Overdue Amount</p>
                <p class="text-lg font-semibold text-red-600 dark:text-red-300">{{ number_format($kpis['overdue_amount'], 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Payments Received</p>
                <p class="text-lg font-semibold text-green-700 dark:text-green-400">{{ number_format($kpis['payments']['current'], 2) }}</p>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Revenue Trend Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Trend</h3>
                <div class="h-72">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>

            <!-- Invoice Status Distribution -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Invoice Status Distribution</h3>
                <div class="h-72">
                    <canvas id="invoiceStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Methods</h3>
            <div class="h-72">
                <canvas id="paymentMethodChart"></canvas>
            </div>
        </div>

        <!-- Customer Retention -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Customer Metrics</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="text-center p-4 bg-brand-50 dark:bg-brand-900/30 rounded-lg">
                    <p class="text-2xl font-bold text-brand-600 dark:text-brand-300">{{ $customerRetention['active_customers'] }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Active Customers</p>
                </div>
                <div class="text-center p-4 bg-green-50 dark:bg-green-900/30 rounded-lg">
                    <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $customerRetention['repeat_customers'] }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Repeat Customers</p>
                </div>
                <div class="text-center p-4 bg-accent-50 dark:bg-accent-900/30 rounded-lg">
                    <p class="text-2xl font-bold text-accent-700 dark:text-accent-300">{{ $customerRetention['repeat_rate'] }}%</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Repeat Rate</p>
                </div>
                <div class="text-center p-4 bg-orange-50 dark:bg-orange-900/30 rounded-lg">
                    <p class="text-2xl font-bold text-orange-700 dark:text-orange-400">{{ number_format($customerRetention['avg_lifetime_value'], 0) }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Avg Lifetime Value</p>
                </div>
                <div class="text-center p-4 bg-red-50 dark:bg-red-900/30 rounded-lg">
                    <p class="text-2xl font-bold text-red-600 dark:text-red-300">{{ $customerRetention['churned_customers'] }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Churned (90+ days)</p>
                </div>
                <div class="text-center p-4 bg-teal-50 dark:bg-teal-900/30 rounded-lg">
                    <p class="text-2xl font-bold text-teal-600 dark:text-teal-400">{{ $customerRetention['retention_rate'] }}%</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Retention Rate</p>
                </div>
            </div>
        </div>

        <!-- Top Selling Items & Top Customers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top Selling Items -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Selling Items</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty Sold</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($topSellingItems as $item)
                                <tr>
                                    <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-200">
                                        {{ $item['name'] }}
                                        @if($item['sku'])
                                            <span class="text-xs text-gray-500 dark:text-gray-400">({{ $item['sku'] }})</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-gray-200">{{ number_format($item['quantity_sold'], 2) }}</td>
                                    <td class="px-3 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-200">{{ number_format($item['total_revenue'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">No sales data available</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Customers -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Customers</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Orders</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Sales</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($salesByCustomer as $customer)
                                <tr>
                                    <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-200">
                                        <a href="{{ route('customers.show', $customer['id']) }}" class="hover:text-brand-600 dark:hover:text-brand-300">
                                            {{ $customer['name'] }}
                                        </a>
                                        @if($customer['company'])
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $customer['company'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-gray-200">{{ $customer['invoice_count'] }}</td>
                                    <td class="px-3 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-200">{{ number_format($customer['total_sales'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">No customer data available</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Overdue Invoices -->
        @if(count($overdueAnalysis) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                Overdue Invoices
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Invoice</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Balance Due</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Due Date</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Days Overdue</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($overdueAnalysis as $invoice)
                            <tr>
                                <td class="px-3 py-2 text-sm">
                                    <a href="{{ route('invoices.show', $invoice['id']) }}" class="text-brand-600 dark:text-brand-300 hover:underline">
                                        {{ $invoice['invoice_number'] }}
                                    </a>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-200">{{ $invoice['customer'] }}</td>
                                <td class="px-3 py-2 text-sm text-right font-medium text-red-600 dark:text-red-300">{{ number_format($invoice['balance_due'], 2) }}</td>
                                <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-gray-200">{{ $invoice['due_date'] }}</td>
                                <td class="px-3 py-2 text-sm text-right">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        {{ $invoice['days_overdue'] > 60 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                           ($invoice['days_overdue'] > 30 ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : 
                                           'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200') }}">
                                        {{ $invoice['days_overdue'] }} days
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <a href="{{ route('payments-received.create', ['invoice_id' => $invoice['id']]) }}" class="text-xs text-brand-600 dark:text-brand-300 hover:underline">
                                        Record Payment
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function analyticsApp() {
            return {
                initCharts() {
                    // Chart.js comes from our own bundle (U14).
                    window.loadChart().then(() => {
                        this.initRevenueTrendChart();
                        this.initInvoiceStatusChart();
                        this.initPaymentMethodChart();
                    });
                },

                // Chart colours from config/brand.php: the same checked colours as
                // the dashboard (income blue, profit/ink grey; status colours only
                // for invoice statuses). Bars rather than pies (dashboard upgrade).
                brandChart: @js(config('brand.chart')),
                dark() { return document.documentElement.classList.contains('dark'); },
                c() { return this.dark() ? this.brandChart.dark : this.brandChart; },
                ink() { return this.dark() ? '#D1D5DB' : '#4B5563'; },
                money(v) { return window.formatMoney(v); },

                initRevenueTrendChart() {
                    const ctx = document.getElementById('revenueTrendChart').getContext('2d');
                    const data = @json($revenueTrends);
                    
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: 'Invoiced',
                                    data: data.revenue,
                                    borderColor: this.c().income,
                                    backgroundColor: this.c().income,
                                    borderWidth: 2,
                                    pointRadius: 3,
                                    tension: 0,
                                },
                                {
                                    label: 'Payments received',
                                    data: data.payments,
                                    borderColor: this.c().profit,
                                    backgroundColor: this.c().profit,
                                    borderWidth: 2,
                                    borderDash: [5, 4],
                                    pointRadius: 3,
                                    tension: 0,
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top', labels: { color: this.ink(), usePointStyle: true } },
                                tooltip: { callbacks: { label: (item) => ' ' + item.dataset.label + ': ' + this.money(item.raw) } },
                            },
                            scales: {
                                x: { ticks: { color: this.ink() }, grid: { display: false } },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        color: this.ink(),
                                        callback: function(value) {
                                            return value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                },

                initInvoiceStatusChart() {
                    const ctx = document.getElementById('invoiceStatusChart').getContext('2d');
                    const data = @json($invoiceStatusDistribution);
                    
                    const colors = this.brandChart.invoice_status;

                    // Bars, coloured by status meaning, labelled on the axis.
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.label),
                            datasets: [{
                                label: 'Invoices',
                                data: data.map(d => d.count),
                                backgroundColor: data.map(d => colors[d.status] || '#9CA3AF'),
                                borderRadius: 4,
                                barPercentage: 0.7,
                                maxBarThickness: 28,
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { beginAtZero: true, ticks: { color: this.ink(), precision: 0 } },
                                y: { ticks: { color: this.ink() }, grid: { display: false } },
                            }
                        }
                    });
                },

                initPaymentMethodChart() {
                    const ctx = document.getElementById('paymentMethodChart').getContext('2d');
                    const data = @json($paymentMethodDistribution);
                    
                    // One colour: the bars are compared by length, not by hue.
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.method),
                            datasets: [{
                                label: 'Received',
                                data: data.map(d => d.total),
                                backgroundColor: this.c().income,
                                borderRadius: 4,
                                barPercentage: 0.7,
                                maxBarThickness: 28,
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: { label: (item) => ' ' + this.money(item.raw) } },
                            },
                            scales: {
                                x: { beginAtZero: true, ticks: { color: this.ink(), callback: (v) => v.toLocaleString() } },
                                y: { ticks: { color: this.ink() }, grid: { display: false } },
                            }
                        }
                    });
                }
            }
        }

        // Handle period selector
        document.getElementById('period').addEventListener('change', function() {
            const customRange = document.getElementById('customDateRange');
            if (this.value === 'custom') {
                customRange.classList.remove('hidden');
            } else {
                customRange.classList.add('hidden');
            }
        });
    </script>
    @endpush
</x-app-layout>
