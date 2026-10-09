<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Tax Liability Report') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
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
                <form method="GET" action="{{ route('reports.tax-liability') }}" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                        <div>
                            <label for="group_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group By</label>
                            <select name="group_by" id="group_by"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                <option value="month" {{ $groupBy === 'month' ? 'selected' : '' }}>Monthly</option>
                                <option value="quarter" {{ $groupBy === 'quarter' ? 'selected' : '' }}>Quarterly</option>
                                <option value="year" {{ $groupBy === 'year' ? 'selected' : '' }}>Yearly</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                </svg>
                                Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Total Tax Collected -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Collected</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($totalTaxCollected, 2) }}</p>
                        </div>
                        <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-full">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        From {{ number_format($totalTaxableSales, 2) }} taxable sales
                    </p>
                </div>
            </div>

            <!-- Total Tax Paid -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Paid</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($totalTaxPaid, 2) }}</p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        From {{ number_format($totalTaxablePurchases, 2) }} taxable purchases
                    </p>
                </div>
            </div>

            <!-- Net Tax Liability -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                Net {{ $totalNetLiability >= 0 ? 'Liability' : 'Credit' }}
                            </p>
                            <p class="text-2xl font-bold {{ $totalNetLiability >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-brand-600 dark:text-brand-300' }}">
                                {{ number_format(abs($totalNetLiability), 2) }}
                            </p>
                        </div>
                        <div class="p-3 {{ $totalNetLiability >= 0 ? 'bg-orange-100 dark:bg-orange-900/30' : 'bg-brand-100 dark:bg-brand-900/30' }} rounded-full">
                            <svg class="w-6 h-6 {{ $totalNetLiability >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-brand-600 dark:text-brand-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Tax Collected - Tax Paid
                    </p>
                </div>
            </div>

            <!-- Effective Tax Rate -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Effective Tax Rate</p>
                            @php
                                $effectiveRate = $totalTaxableSales > 0 ? ($totalTaxCollected / $totalTaxableSales) * 100 : 0;
                            @endphp
                            <p class="text-2xl font-bold text-accent-700 dark:text-accent-300">{{ number_format($effectiveRate, 2) }}%</p>
                        </div>
                        <div class="p-3 bg-accent-100 dark:bg-accent-900/30 rounded-full">
                            <svg class="w-6 h-6 text-accent-700 dark:text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Average on taxable sales
                    </p>
                </div>
            </div>
        </div>

        <!-- Period by Period Breakdown -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tax Liability by Period</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Period</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Taxable Sales</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Collected</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Taxable Purchases</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Paid</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Net Liability</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cumulative</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($periodsWithCumulative as $period)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $period['period_label'] }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($period['taxable_sales'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400 font-medium">{{ number_format($period['tax_collected'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($period['taxable_purchases'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-green-600 dark:text-green-400 font-medium">{{ number_format($period['tax_paid'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold {{ $period['net_liability'] >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-brand-600 dark:text-brand-300' }}">
                                    {{ $period['net_liability'] >= 0 ? '' : '(' }}{{ number_format(abs($period['net_liability']), 2) }}{{ $period['net_liability'] >= 0 ? '' : ')' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-bold {{ $period['cumulative_liability'] >= 0 ? 'text-orange-700 dark:text-orange-300' : 'text-brand-700 dark:text-brand-300' }}">
                                    {{ $period['cumulative_liability'] >= 0 ? '' : '(' }}{{ number_format(abs($period['cumulative_liability']), 2) }}{{ $period['cumulative_liability'] >= 0 ? '' : ')' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No tax data for this period</td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($periodsWithCumulative->count() > 0)
                        <tfoot class="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-white">Total</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($totalTaxableSales, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-red-600 dark:text-red-400">{{ number_format($totalTaxCollected, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($totalTaxablePurchases, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-green-600 dark:text-green-400">{{ number_format($totalTaxPaid, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold {{ $totalNetLiability >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-brand-600 dark:text-brand-300' }}">
                                    {{ $totalNetLiability >= 0 ? '' : '(' }}{{ number_format(abs($totalNetLiability), 2) }}{{ $totalNetLiability >= 0 ? '' : ')' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-bold {{ $totalNetLiability >= 0 ? 'text-orange-700 dark:text-orange-300' : 'text-brand-700 dark:text-brand-300' }}">
                                    {{ $totalNetLiability >= 0 ? '' : '(' }}{{ number_format(abs($totalNetLiability), 2) }}{{ $totalNetLiability >= 0 ? '' : ')' }}
                                </td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Tax by Rate Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Output Tax by Rate -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tax Collected by Rate</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Rate</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Taxable Amount</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Collected</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($taxByRateOutput as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            {{ number_format($row->tax_rate, 2) }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($row->taxable_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-red-600 dark:text-red-400">{{ number_format($row->tax_amount, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No tax data</td>
                                </tr>
                                @endforelse
                            </tbody>
                            @if($taxByRateOutput->count() > 0)
                            <tfoot class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-white">Total</td>
                                    <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($taxByRateOutput->sum('taxable_amount'), 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-bold text-red-600 dark:text-red-400">{{ number_format($taxByRateOutput->sum('tax_amount'), 2) }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            <!-- Input Tax by Rate -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tax Paid by Rate</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Rate</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Taxable Amount</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Paid</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($taxByRateInput as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            {{ number_format($row->tax_rate, 2) }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($row->taxable_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-green-600 dark:text-green-400">{{ number_format($row->tax_amount, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No tax data</td>
                                </tr>
                                @endforelse
                            </tbody>
                            @if($taxByRateInput->count() > 0)
                            <tfoot class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-white">Total</td>
                                    <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($taxByRateInput->sum('taxable_amount'), 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-bold text-green-600 dark:text-green-400">{{ number_format($taxByRateInput->sum('tax_amount'), 2) }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visual Comparison -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tax Liability Visualization</h3>
                <div class="space-y-4">
                    @foreach($periodsWithCumulative as $period)
                    <div class="flex items-center">
                        <div class="w-24 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $period['period_label'] }}</div>
                        <div class="flex-1 flex items-center space-x-2">
                            @php
                                $maxValue = max($totalTaxCollected, $totalTaxPaid, 1);
                                $collectedWidth = ($period['tax_collected'] / $maxValue) * 100;
                                $paidWidth = ($period['tax_paid'] / $maxValue) * 100;
                            @endphp
                            <div class="flex-1 flex flex-col space-y-1">
                                <div class="flex items-center">
                                    <span class="w-16 text-xs text-gray-500 dark:text-gray-400">Collected</span>
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                        <div class="bg-red-500 h-4 rounded-full transition-all duration-500 flex items-center justify-end pr-2" style="width: {{ max($collectedWidth, 2) }}%">
                                            @if($collectedWidth > 15)
                                            <span class="text-xs text-white font-medium">{{ number_format($period['tax_collected'], 0) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-16 text-xs text-gray-500 dark:text-gray-400">Paid</span>
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                        <div class="bg-green-500 h-4 rounded-full transition-all duration-500 flex items-center justify-end pr-2" style="width: {{ max($paidWidth, 2) }}%">
                                            @if($paidWidth > 15)
                                            <span class="text-xs text-white font-medium">{{ number_format($period['tax_paid'], 0) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="w-24 text-right">
                                <span class="text-sm font-semibold {{ $period['net_liability'] >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-brand-600 dark:text-brand-300' }}">
                                    {{ $period['net_liability'] >= 0 ? '+' : '-' }}{{ number_format(abs($period['net_liability']), 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Tax Liability Status -->
        <div class="{{ $totalNetLiability >= 0 ? 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800' : 'bg-brand-50 dark:bg-brand-900/20 border-brand-200 dark:border-brand-800' }} border rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    @if($totalNetLiability >= 0)
                        <svg class="h-5 w-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    @else
                        <svg class="h-5 w-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    @endif
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium {{ $totalNetLiability >= 0 ? 'text-orange-800 dark:text-orange-200' : 'text-brand-800 dark:text-brand-200' }}">
                        Tax {{ $totalNetLiability >= 0 ? 'Liability Outstanding' : 'Credit Available' }}
                    </h3>
                    <p class="mt-1 text-sm {{ $totalNetLiability >= 0 ? 'text-orange-700 dark:text-orange-300' : 'text-brand-700 dark:text-brand-300' }}">
                        @if($totalNetLiability >= 0)
                            You have a net tax liability of <strong>{{ number_format($totalNetLiability, 2) }}</strong> to remit to tax authorities for this period.
                        @else
                            You have a net tax credit of <strong>{{ number_format(abs($totalNetLiability), 2) }}</strong> that may be refundable or carried forward.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Report Info -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Report Period: <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</span>
                to <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
                | Grouped by: <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst($groupBy) }}</span>
            </p>
        </div>
    </div>
</x-app-layout>
