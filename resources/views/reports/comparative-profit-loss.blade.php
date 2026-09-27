<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Comparative Profit & Loss') }}
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
        <!-- Period Selection -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <form method="GET" action="{{ route('reports.comparative.profit-loss') }}" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="lg:col-span-1">
                            <label for="comparison_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Comparison Type</label>
                            <select name="comparison_type" id="comparison_type" onchange="toggleCustomDates(this.value)"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                <option value="month" {{ $comparisonType === 'month' ? 'selected' : '' }}>Month over Month</option>
                                <option value="quarter" {{ $comparisonType === 'quarter' ? 'selected' : '' }}>Quarter over Quarter</option>
                                <option value="year" {{ $comparisonType === 'year' ? 'selected' : '' }}>Year over Year</option>
                                <option value="ytd" {{ $comparisonType === 'ytd' ? 'selected' : '' }}>Year to Date</option>
                                <option value="custom" {{ $comparisonType === 'custom' ? 'selected' : '' }}>Custom Periods</option>
                            </select>
                        </div>
                        
                        <div id="customDates" class="lg:col-span-4 {{ $comparisonType !== 'custom' ? 'hidden' : '' }}">
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label for="current_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Start</label>
                                    <input type="date" name="current_start" id="current_start" value="{{ request('current_start', now()->startOfMonth()->format('Y-m-d')) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                </div>
                                <div>
                                    <label for="current_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current End</label>
                                    <input type="date" name="current_end" id="current_end" value="{{ request('current_end', now()->format('Y-m-d')) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                </div>
                                <div>
                                    <label for="previous_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Previous Start</label>
                                    <input type="date" name="previous_start" id="previous_start" value="{{ request('previous_start', now()->subMonth()->startOfMonth()->format('Y-m-d')) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                </div>
                                <div>
                                    <label for="previous_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Previous End</label>
                                    <input type="date" name="previous_end" id="previous_end" value="{{ request('previous_end', now()->subMonth()->endOfMonth()->format('Y-m-d')) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Compare
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Net Profit Comparison -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @foreach($periodData as $key => $period)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $period['label'] }}</h3>
                        @if($key === 'current')
                            <span class="px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full">Current</span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 rounded-full">Previous</span>
                        @endif
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Net {{ $period['netProfit'] >= 0 ? 'Profit' : 'Loss' }}</p>
                        <p class="text-3xl font-bold {{ $period['netProfit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $period['netProfit'] >= 0 ? '' : '-' }}{{ number_format(abs($period['netProfit']), 2) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ number_format($period['profitMargin'], 1) }}% margin
                        </p>
                    </div>
                </div>
            </div>
            @endforeach

            <!-- Change Summary -->
            @if(isset($changes['netProfit']))
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Change</h3>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Difference</p>
                        <p class="text-3xl font-bold {{ $changes['netProfit']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $changes['netProfit']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['netProfit']['difference'], 2) }}
                        </p>
                        <div class="flex items-center justify-center mt-2">
                            @if($changes['netProfit']['improved'])
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                </svg>
                            @endif
                            <span class="ml-1 text-sm {{ $changes['netProfit']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ number_format(abs($changes['netProfit']['percentChange']), 1) }}%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Detailed Comparison Table -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Period Comparison Details</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Metric
                                </th>
                                @foreach($periodData as $key => $period)
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    {{ $period['label'] }}
                                </th>
                                @endforeach
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Change
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    % Change
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Revenue -->
                            <tr class="bg-green-50 dark:bg-green-900/20">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    Total Revenue
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600 dark:text-green-400 font-medium">
                                    {{ number_format($period['revenue'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['revenue']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['revenue']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['revenue']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['revenue']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['revenue']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['revenue']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['revenue']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Operating Expenses -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Operating Expenses
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400">
                                    ({{ number_format($period['expenses'], 2) }})
                                </td>
                                @endforeach
                                @if(isset($changes['expenses']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['expenses']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['expenses']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['expenses']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['expenses']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['expenses']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['expenses']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Cost of Goods -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Cost of Goods/Services
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400">
                                    ({{ number_format($period['billsPaid'], 2) }})
                                </td>
                                @endforeach
                                @if(isset($changes['billsPaid']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['billsPaid']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['billsPaid']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['billsPaid']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['billsPaid']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['billsPaid']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['billsPaid']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Payroll -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Payroll
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400">
                                    ({{ number_format($period['payroll'], 2) }})
                                </td>
                                @endforeach
                                @if(isset($changes['payroll']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['payroll']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['payroll']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['payroll']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['payroll']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['payroll']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['payroll']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Total Expenses -->
                            <tr class="bg-red-50 dark:bg-red-900/20">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    Total Expenses
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400 font-medium">
                                    ({{ number_format($period['totalExpenses'], 2) }})
                                </td>
                                @endforeach
                                @if(isset($changes['totalExpenses']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['totalExpenses']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['totalExpenses']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['totalExpenses']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['totalExpenses']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['totalExpenses']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['totalExpenses']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Net Profit -->
                            <tr class="bg-gray-100 dark:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">
                                    Net Profit/Loss
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold {{ $period['netProfit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $period['netProfit'] >= 0 ? '' : '-' }}{{ number_format(abs($period['netProfit']), 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['netProfit']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold {{ $changes['netProfit']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['netProfit']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['netProfit']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $changes['netProfit']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['netProfit']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['netProfit']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Profit Margin -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    Profit Margin
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $period['profitMargin'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($period['profitMargin'], 1) }}%
                                </td>
                                @endforeach
                                @if(isset($changes['profitMargin']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['profitMargin']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['profitMargin']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['profitMargin']['difference'], 1) }}pp
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">
                                    -
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Visual Comparison Chart -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Visual Comparison</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Revenue vs Expenses Bar -->
                    @foreach($periodData as $key => $period)
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $period['label'] }}</h4>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">Revenue</span>
                                    <span class="text-green-600 dark:text-green-400">{{ number_format($period['revenue'], 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                    @php
                                        $maxValue = max(array_column($periodData, 'revenue'));
                                        $revenuePercent = $maxValue > 0 ? ($period['revenue'] / $maxValue) * 100 : 0;
                                    @endphp
                                    <div class="bg-green-500 h-4 rounded-full transition-all duration-500" style="width: {{ $revenuePercent }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">Expenses</span>
                                    <span class="text-red-600 dark:text-red-400">{{ number_format($period['totalExpenses'], 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                    @php
                                        $maxExpense = max(array_column($periodData, 'totalExpenses'));
                                        $expensePercent = $maxExpense > 0 ? ($period['totalExpenses'] / $maxExpense) * 100 : 0;
                                    @endphp
                                    <div class="bg-red-500 h-4 rounded-full transition-all duration-500" style="width: {{ $expensePercent }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Analysis Summary -->
        @if(isset($changes['netProfit']))
        <div class="{{ $changes['netProfit']['improved'] ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' }} border rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    @if($changes['netProfit']['improved'])
                        <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    @else
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                        </svg>
                    @endif
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium {{ $changes['netProfit']['improved'] ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                        Performance {{ $changes['netProfit']['improved'] ? 'Improved' : 'Declined' }}
                    </h3>
                    <p class="mt-1 text-sm {{ $changes['netProfit']['improved'] ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                        @if($changes['netProfit']['improved'])
                            Net profit {{ $changes['netProfit']['difference'] >= 0 ? 'increased' : 'improved' }} by {{ number_format(abs($changes['netProfit']['difference']), 2) }} 
                            ({{ number_format(abs($changes['netProfit']['percentChange']), 1) }}%) compared to the previous period.
                        @else
                            Net profit decreased by {{ number_format(abs($changes['netProfit']['difference']), 2) }} 
                            ({{ number_format(abs($changes['netProfit']['percentChange']), 1) }}%) compared to the previous period.
                        @endif
                    </p>
                </div>
            </div>
        </div>
        @endif

        <!-- Report Info -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Comparing: <span class="font-medium text-gray-900 dark:text-white">{{ $periodData['current']['label'] ?? 'Current' }}</span> 
                vs <span class="font-medium text-gray-900 dark:text-white">{{ $periodData['previous']['label'] ?? 'Previous' }}</span>
            </p>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function toggleCustomDates(value) {
            const customDates = document.getElementById('customDates');
            if (value === 'custom') {
                customDates.classList.remove('hidden');
            } else {
                customDates.classList.add('hidden');
            }
        }
    </script>
    @endpush
</x-app-layout>
