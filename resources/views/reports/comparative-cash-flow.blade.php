<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Comparative Cash Flow') }}
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
                <form method="GET" action="{{ route('reports.comparative.cash-flow') }}" class="space-y-4">
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

        <!-- Net Cash Flow Comparison -->
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
                        <p class="text-sm text-gray-500 dark:text-gray-400">Net Cash Flow</p>
                        <p class="text-3xl font-bold {{ $period['netCashFlow'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $period['netCashFlow'] >= 0 ? '+' : '' }}{{ number_format($period['netCashFlow'], 2) }}
                        </p>
                        <div class="mt-2 flex justify-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                            <span class="text-green-600 dark:text-green-400">In: {{ number_format($period['totalInflows'], 2) }}</span>
                            <span class="text-red-600 dark:text-red-400">Out: {{ number_format($period['totalOutflows'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            <!-- Change Summary -->
            @if(isset($changes['netCashFlow']))
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Change</h3>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Difference</p>
                        <p class="text-3xl font-bold {{ $changes['netCashFlow']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $changes['netCashFlow']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['netCashFlow']['difference'], 2) }}
                        </p>
                        <div class="flex items-center justify-center mt-2">
                            @if($changes['netCashFlow']['improved'])
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                </svg>
                            @endif
                            <span class="ml-1 text-sm {{ $changes['netCashFlow']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ number_format(abs($changes['netCashFlow']['percentChange']), 1) }}%
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Cash Flow Comparison</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Category
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
                            <!-- Cash Inflows Section -->
                            <tr class="bg-green-50 dark:bg-green-900/20">
                                <td colspan="5" class="px-6 py-3 text-sm font-semibold text-green-800 dark:text-green-200">
                                    CASH INFLOWS
                                </td>
                            </tr>
                            
                            <!-- Payments Received -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Payments Received
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600 dark:text-green-400">
                                    {{ number_format($period['paymentsReceived'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['paymentsReceived']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['paymentsReceived']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['paymentsReceived']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['paymentsReceived']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['paymentsReceived']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['paymentsReceived']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['paymentsReceived']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Total Inflows -->
                            <tr class="bg-green-100 dark:bg-green-900/30 font-semibold">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-800 dark:text-green-200">
                                    Total Cash Inflows
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-800 dark:text-green-200">
                                    {{ number_format($period['totalInflows'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['totalInflows']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['totalInflows']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['totalInflows']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['totalInflows']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $changes['totalInflows']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['totalInflows']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['totalInflows']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Cash Outflows Section -->
                            <tr class="bg-red-50 dark:bg-red-900/20">
                                <td colspan="5" class="px-6 py-3 text-sm font-semibold text-red-800 dark:text-red-200">
                                    CASH OUTFLOWS
                                </td>
                            </tr>

                            <!-- Payments Made -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Payments to Vendors
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400">
                                    ({{ number_format($period['paymentsMade'], 2) }})
                                </td>
                                @endforeach
                                @if(isset($changes['paymentsMade']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['paymentsMade']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['paymentsMade']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['paymentsMade']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['paymentsMade']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['paymentsMade']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['paymentsMade']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Expenses Paid -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Operating Expenses
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400">
                                    ({{ number_format($period['expensesPaid'], 2) }})
                                </td>
                                @endforeach
                                @if(isset($changes['expensesPaid']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['expensesPaid']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['expensesPaid']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['expensesPaid']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['expensesPaid']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['expensesPaid']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['expensesPaid']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Payroll Paid -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Payroll
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400">
                                    ({{ number_format($period['payrollPaid'], 2) }})
                                </td>
                                @endforeach
                                @if(isset($changes['payrollPaid']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['payrollPaid']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['payrollPaid']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['payrollPaid']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['payrollPaid']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['payrollPaid']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['payrollPaid']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Total Outflows -->
                            <tr class="bg-red-100 dark:bg-red-900/30 font-semibold">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-800 dark:text-red-200">
                                    Total Cash Outflows
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-800 dark:text-red-200">
                                    ({{ number_format($period['totalOutflows'], 2) }})
                                </td>
                                @endforeach
                                @if(isset($changes['totalOutflows']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['totalOutflows']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['totalOutflows']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['totalOutflows']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $changes['totalOutflows']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['totalOutflows']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['totalOutflows']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Net Cash Flow -->
                            <tr class="bg-gray-100 dark:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">
                                    Net Cash Flow
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold {{ $period['netCashFlow'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $period['netCashFlow'] >= 0 ? '+' : '' }}{{ number_format($period['netCashFlow'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['netCashFlow']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold {{ $changes['netCashFlow']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['netCashFlow']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['netCashFlow']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $changes['netCashFlow']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['netCashFlow']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['netCashFlow']['percentChange'], 1) }}%
                                    </span>
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

        <!-- Visual Comparison -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Cash Flow Visualization</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @foreach($periodData as $key => $period)
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ $period['label'] }}</h4>
                        <div class="space-y-4">
                            <!-- Cash Inflows -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">Cash Inflows</span>
                                    <span class="text-green-600 dark:text-green-400 font-medium">{{ number_format($period['totalInflows'], 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6">
                                    @php
                                        $maxInflows = max(array_column($periodData, 'totalInflows'));
                                        $inflowsPercent = $maxInflows > 0 ? ($period['totalInflows'] / $maxInflows) * 100 : 0;
                                    @endphp
                                    <div class="bg-green-500 h-6 rounded-full flex items-center justify-end pr-2 transition-all duration-500" style="width: {{ max($inflowsPercent, 5) }}%">
                                        @if($inflowsPercent > 20)
                                        <span class="text-xs text-white font-medium">{{ number_format($inflowsPercent, 0) }}%</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Cash Outflows -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">Cash Outflows</span>
                                    <span class="text-red-600 dark:text-red-400 font-medium">{{ number_format($period['totalOutflows'], 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6">
                                    @php
                                        $maxOutflows = max(array_column($periodData, 'totalOutflows'));
                                        $outflowsPercent = $maxOutflows > 0 ? ($period['totalOutflows'] / $maxOutflows) * 100 : 0;
                                    @endphp
                                    <div class="bg-red-500 h-6 rounded-full flex items-center justify-end pr-2 transition-all duration-500" style="width: {{ max($outflowsPercent, 5) }}%">
                                        @if($outflowsPercent > 20)
                                        <span class="text-xs text-white font-medium">{{ number_format($outflowsPercent, 0) }}%</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Net Cash Flow -->
                            <div class="pt-2 border-t border-gray-200 dark:border-gray-600">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400 font-medium">Net Cash Flow</span>
                                    <span class="{{ $period['netCashFlow'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} font-bold">
                                        {{ $period['netCashFlow'] >= 0 ? '+' : '' }}{{ number_format($period['netCashFlow'], 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Analysis Summary -->
        @if(isset($changes['netCashFlow']))
        <div class="{{ $changes['netCashFlow']['improved'] ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' }} border rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    @if($changes['netCashFlow']['improved'])
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
                    <h3 class="text-sm font-medium {{ $changes['netCashFlow']['improved'] ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                        Cash Position {{ $changes['netCashFlow']['improved'] ? 'Improved' : 'Declined' }}
                    </h3>
                    <p class="mt-1 text-sm {{ $changes['netCashFlow']['improved'] ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                        @if($changes['netCashFlow']['improved'])
                            Net cash flow {{ $changes['netCashFlow']['difference'] >= 0 ? 'increased' : 'improved' }} by {{ number_format(abs($changes['netCashFlow']['difference']), 2) }} 
                            ({{ number_format(abs($changes['netCashFlow']['percentChange']), 1) }}%) compared to the previous period.
                        @else
                            Net cash flow decreased by {{ number_format(abs($changes['netCashFlow']['difference']), 2) }} 
                            ({{ number_format(abs($changes['netCashFlow']['percentChange']), 1) }}%) compared to the previous period.
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
