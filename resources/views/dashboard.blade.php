<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="space-y-4 sm:space-y-6">
        <!-- Welcome Section -->
        <div>
            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">
                Welcome back, {{ auth()->user()->name }}!
            </h3>
            <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400">Here's what's happening with your business today.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6">
            <!-- Total Revenue -->
            @can('total-revenue dashboard-widgets')
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-2 sm:p-3">
                            <svg class="h-5 w-5 sm:h-6 sm:w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-3 sm:ml-5 min-w-0 flex-1">
                            <dl>
                                <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Revenue</dt>
                                <dd class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate">{{ number_format($totalRevenue ?? 0, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Invoices -->
            @endcan
            @can('outstanding-receivables dashboard-widgets')
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-500 rounded-md p-2 sm:p-3">
                            <svg class="h-5 w-5 sm:h-6 sm:w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-3 sm:ml-5 min-w-0 flex-1">
                            <dl>
                                <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Outstanding</dt>
                                <dd class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate">{{ number_format($accountsReceivable ?? 0, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Expenses -->
            @endcan
            @can('monthly-expenses dashboard-widgets')
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-500 rounded-md p-2 sm:p-3">
                            <svg class="h-5 w-5 sm:h-6 sm:w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-3 sm:ml-5 min-w-0 flex-1">
                            <dl>
                                <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Expenses</dt>
                                <dd class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate">{{ number_format($monthlyExpenses ?? 0, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Employees -->
            @endcan
            @can('employees-count dashboard-widgets')
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-500 rounded-md p-2 sm:p-3">
                            <svg class="h-5 w-5 sm:h-6 sm:w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-3 sm:ml-5 min-w-0 flex-1">
                            <dl>
                                <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Employees</dt>
                                <dd class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">{{ $totalEmployees ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            @endcan
        </div>

        <!-- Quick Actions - Mobile Friendly Grid -->
        @can('quick-actions dashboard-widgets')
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-4 sm:p-6">
                <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4">Quick Actions</h4>
                <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-4">
                    <a href="{{ route('invoices.create') }}" class="flex flex-col items-center p-2 sm:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-blue-600 dark:text-blue-400 mb-1 sm:mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 text-center">Invoice</span>
                    </a>
                    <a href="{{ route('expenses.create') }}" class="flex flex-col items-center p-2 sm:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-red-600 dark:text-red-400 mb-1 sm:mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 text-center">Expense</span>
                    </a>
                    <a href="{{ route('customers.create') }}" class="flex flex-col items-center p-2 sm:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-600 dark:text-green-400 mb-1 sm:mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 text-center">Customer</span>
                    </a>
                    <a href="{{ route('items.create') }}" class="flex flex-col items-center p-2 sm:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-purple-600 dark:text-purple-400 mb-1 sm:mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 text-center">Item</span>
                    </a>
                    <a href="{{ route('employees.create') }}" class="flex flex-col items-center p-2 sm:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-indigo-600 dark:text-indigo-400 mb-1 sm:mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 text-center">Employee</span>
                    </a>
                    <a href="{{ route('reports.index') }}" class="flex flex-col items-center p-2 sm:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-yellow-600 dark:text-yellow-400 mb-1 sm:mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 text-center">Reports</span>
                    </a>
                    <a href="{{ route('analytics.index') }}" class="flex flex-col items-center p-2 sm:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-teal-600 dark:text-teal-400 mb-1 sm:mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                        </svg>
                        <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 text-center">Analytics</span>
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Revenue vs Expenses Chart -->
        @can('revenue-chart dashboard-widgets')
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Revenue vs Expenses ({{ now()->year }})</h4>
                    <div class="flex items-center space-x-4 text-sm">
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                            <span class="text-gray-600 dark:text-gray-400">Revenue</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                            <span class="text-gray-600 dark:text-gray-400">Expenses</span>
                        </div>
                    </div>
                </div>
                <div class="relative" style="height: 300px;">
                    <canvas id="revenueExpenseChart"></canvas>
                </div>
            </div>
        </div>
        @endcan

        <!-- Recent Activity Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <!-- Recent Invoices -->
            @can('recent-invoices dashboard-widgets')
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Recent Invoices</h4>
                        <a href="{{ route('invoices.index') }}" class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">View All</a>
                    </div>
                    <div class="space-y-2 sm:space-y-3">
                        @forelse($recentInvoices ?? [] as $invoice)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <div class="min-w-0 flex-1 mr-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $invoice->invoice_number }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $invoice->customer->name ?? 'N/A' }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ number_format($invoice->total, 2) }}</p>
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full 
                                        {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : '' }}
                                        {{ $invoice->status === 'unpaid' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : '' }}
                                        {{ $invoice->status === 'overdue' ? 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' : '' }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No recent invoices</p>
                        @endforelse
                    </div>
                </div>
            </div>
            @endcan

            <!-- Pending Bills -->
            @can('pending-bills dashboard-widgets')
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Pending Bills</h4>
                        <a href="{{ route('bills.index') }}" class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">View All</a>
                    </div>
                    <div class="space-y-2 sm:space-y-3">
                        @forelse($pendingBills ?? [] as $bill)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <div class="min-w-0 flex-1 mr-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $bill->bill_number }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $bill->vendor->company_name ?? 'N/A' }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ number_format($bill->balance_due, 2) }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Due: {{ $bill->due_date?->format('M d') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No pending bills</p>
                        @endforelse
                    </div>
                </div>
            </div>
            @endcan

            <!-- Low Stock Items -->
            @can('low-stock dashboard-widgets')
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Low Stock Items</h4>
                        <a href="{{ route('inventory.index') }}" class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">View All</a>
                    </div>
                    <div class="space-y-2 sm:space-y-3">
                        @forelse($lowStockItems ?? [] as $inventory)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <div class="min-w-0 flex-1 mr-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $inventory->item->name ?? 'N/A' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">SKU: {{ $inventory->item->sku ?? 'N/A' }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $inventory->quantity }} units</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Reorder: {{ $inventory->item->reorder_level ?? 0 }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">All items are well stocked</p>
                        @endforelse
                    </div>
                </div>
            </div>
            @endcan
        </div>
    </div>

    @push('scripts')
    @can('revenue-chart dashboard-widgets')
    <script nonce="{{ app('csp-nonce') }}">
        // Chart.js comes from our own bundle (U14).
        document.addEventListener('DOMContentLoaded', () => window.loadChart().then(function (Chart) {
            const ctx = document.getElementById('revenueExpenseChart').getContext('2d');
            const isDarkMode = document.documentElement.classList.contains('dark');
            
            const monthlyData = @json($monthlyTrends ?? []);
            
            const labels = monthlyData.map(item => item.month);
            const revenueData = monthlyData.map(item => item.revenue);
            const expenseData = monthlyData.map(item => item.expenses);
            
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            const textColor = isDarkMode ? '#9CA3AF' : '#6B7280';
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue',
                            data: revenueData,
                            backgroundColor: 'rgba(34, 197, 94, 0.8)',
                            borderColor: 'rgb(34, 197, 94)',
                            borderWidth: 1,
                            borderRadius: 4,
                            barPercentage: 0.8,
                            categoryPercentage: 0.9
                        },
                        {
                            label: 'Expenses',
                            data: expenseData,
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderColor: 'rgb(239, 68, 68)',
                            borderWidth: 1,
                            borderRadius: 4,
                            barPercentage: 0.8,
                            categoryPercentage: 0.9
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: isDarkMode ? '#374151' : '#ffffff',
                            titleColor: isDarkMode ? '#F9FAFB' : '#111827',
                            bodyColor: isDarkMode ? '#D1D5DB' : '#4B5563',
                            borderColor: isDarkMode ? '#4B5563' : '#E5E7EB',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + window.formatMoney(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: textColor,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: gridColor
                            },
                            ticks: {
                                color: textColor,
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    const symbol = @js(\App\Support\Money::symbol());
                                    if (value >= 1000000) {
                                        return symbol + (value / 1000000).toFixed(1) + 'M';
                                    } else if (value >= 1000) {
                                        return symbol + (value / 1000).toFixed(0) + 'K';
                                    }
                                    return symbol + value;
                                }
                            }
                        }
                    }
                }
            });
        }));
    </script>
    @endcan
    @endpush
</x-app-layout>
