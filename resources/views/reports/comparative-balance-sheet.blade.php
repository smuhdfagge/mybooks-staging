<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Comparative Balance Sheet') }}
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
                <form method="GET" action="{{ route('reports.comparative.balance-sheet') }}" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="lg:col-span-1">
                            <label for="comparison_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Comparison Type</label>
                            <select name="comparison_type" id="comparison_type" data-call="toggleCustomDates" data-pass-value
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
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
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                </div>
                                <div>
                                    <label for="current_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current End</label>
                                    <input type="date" name="current_end" id="current_end" value="{{ request('current_end', now()->format('Y-m-d')) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                </div>
                                <div>
                                    <label for="previous_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Previous Start</label>
                                    <input type="date" name="previous_start" id="previous_start" value="{{ request('previous_start', now()->subMonth()->startOfMonth()->format('Y-m-d')) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                </div>
                                <div>
                                    <label for="previous_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Previous End</label>
                                    <input type="date" name="previous_end" id="previous_end" value="{{ request('previous_end', now()->subMonth()->endOfMonth()->format('Y-m-d')) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Compare
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Total Assets Comparison -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @foreach($periodData as $key => $period)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $period['label'] }}</h3>
                        @if($key === 'current')
                            <span class="px-2 py-1 text-xs font-semibold bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200 rounded-full">Current</span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 rounded-full">Previous</span>
                        @endif
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Assets</p>
                        <p class="text-3xl font-bold text-brand-600 dark:text-brand-300">
                            {{ number_format($period['totalAssets'], 2) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            As of {{ \Carbon\Carbon::parse($period['asOf'])->format('M d, Y') }}
                        </p>
                    </div>
                </div>
            </div>
            @endforeach

            <!-- Change Summary -->
            @if(isset($changes['totalAssets']))
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Change</h3>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Difference</p>
                        <p class="text-3xl font-bold {{ $changes['totalAssets']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $changes['totalAssets']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['totalAssets']['difference'], 2) }}
                        </p>
                        <div class="flex items-center justify-center mt-2">
                            @if($changes['totalAssets']['improved'])
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                </svg>
                            @endif
                            <span class="ml-1 text-sm {{ $changes['totalAssets']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ number_format(abs($changes['totalAssets']['percentChange']), 1) }}%
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Balance Sheet Comparison</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Account
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
                            <!-- Assets Section -->
                            <tr class="bg-brand-50 dark:bg-brand-900/20">
                                <td colspan="5" class="px-6 py-3 text-sm font-semibold text-brand-800 dark:text-brand-200">
                                    ASSETS
                                </td>
                            </tr>
                            
                            <!-- Accounts Receivable -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Accounts Receivable
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                    {{ number_format($period['accountsReceivable'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['accountsReceivable']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['accountsReceivable']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['accountsReceivable']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['accountsReceivable']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['accountsReceivable']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['accountsReceivable']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['accountsReceivable']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Total Assets -->
                            <tr class="bg-brand-100 dark:bg-brand-900/30 font-semibold">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-800 dark:text-brand-200">
                                    Total Assets
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-brand-800 dark:text-brand-200">
                                    {{ number_format($period['totalAssets'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['totalAssets']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['totalAssets']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['totalAssets']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['totalAssets']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $changes['totalAssets']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['totalAssets']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['totalAssets']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Liabilities Section -->
                            <tr class="bg-red-50 dark:bg-red-900/20">
                                <td colspan="5" class="px-6 py-3 text-sm font-semibold text-red-800 dark:text-red-200">
                                    LIABILITIES
                                </td>
                            </tr>

                            <!-- Accounts Payable -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Accounts Payable
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                    {{ number_format($period['accountsPayable'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['accountsPayable']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['accountsPayable']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['accountsPayable']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['accountsPayable']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['accountsPayable']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['accountsPayable']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['accountsPayable']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Equity Section -->
                            <tr class="bg-green-50 dark:bg-green-900/20">
                                <td colspan="5" class="px-6 py-3 text-sm font-semibold text-green-800 dark:text-green-200">
                                    EQUITY
                                </td>
                            </tr>

                            <!-- Retained Earnings -->
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white pl-10">
                                    Retained Earnings
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                    {{ number_format($period['equity'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['equity']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['equity']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['equity']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['equity']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $changes['equity']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['equity']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['equity']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">-</td>
                                @endif
                            </tr>

                            <!-- Total Liabilities + Equity -->
                            <tr class="bg-gray-100 dark:bg-gray-700 font-semibold">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    Total Liabilities & Equity
                                </td>
                                @foreach($periodData as $period)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                    {{ number_format($period['totalLiabilitiesEquity'], 2) }}
                                </td>
                                @endforeach
                                @if(isset($changes['totalLiabilitiesEquity']))
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $changes['totalLiabilitiesEquity']['improved'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $changes['totalLiabilitiesEquity']['difference'] >= 0 ? '+' : '' }}{{ number_format($changes['totalLiabilitiesEquity']['difference'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $changes['totalLiabilitiesEquity']['improved'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                        {{ $changes['totalLiabilitiesEquity']['percentChange'] >= 0 ? '+' : '' }}{{ number_format($changes['totalLiabilitiesEquity']['percentChange'], 1) }}%
                                    </span>
                                </td>
                                @else
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">-</td>
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Visual Comparison</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @foreach($periodData as $key => $period)
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ $period['label'] }}</h4>
                        <div class="space-y-4">
                            <!-- Assets Bar -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">Assets</span>
                                    <span class="text-brand-600 dark:text-brand-300 font-medium">{{ number_format($period['totalAssets'], 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6">
                                    @php
                                        $maxAssets = max(array_column($periodData, 'totalAssets'));
                                        $assetsPercent = $maxAssets > 0 ? ($period['totalAssets'] / $maxAssets) * 100 : 0;
                                    @endphp
                                    <div class="bg-brand-500 h-6 rounded-full flex items-center justify-end pr-2 transition-all duration-500" style="width: {{ max($assetsPercent, 5) }}%">
                                        @if($assetsPercent > 20)
                                        <span class="text-xs text-white font-medium">{{ number_format($assetsPercent, 0) }}%</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Liabilities Bar -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">Liabilities</span>
                                    <span class="text-red-600 dark:text-red-400 font-medium">{{ number_format($period['accountsPayable'], 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6">
                                    @php
                                        $maxLiabilities = max(array_column($periodData, 'accountsPayable'));
                                        $liabilitiesPercent = $maxLiabilities > 0 ? ($period['accountsPayable'] / $maxLiabilities) * 100 : 0;
                                    @endphp
                                    <div class="bg-red-500 h-6 rounded-full flex items-center justify-end pr-2 transition-all duration-500" style="width: {{ max($liabilitiesPercent, 5) }}%">
                                        @if($liabilitiesPercent > 20)
                                        <span class="text-xs text-white font-medium">{{ number_format($liabilitiesPercent, 0) }}%</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Equity Bar -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">Equity</span>
                                    <span class="{{ $period['equity'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} font-medium">
                                        {{ $period['equity'] >= 0 ? '' : '-' }}{{ number_format(abs($period['equity']), 2) }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6">
                                    @php
                                        $allEquity = array_column($periodData, 'equity');
                                        $maxEquity = max(array_map('abs', $allEquity));
                                        $equityPercent = $maxEquity > 0 ? (abs($period['equity']) / $maxEquity) * 100 : 0;
                                    @endphp
                                    <div class="{{ $period['equity'] >= 0 ? 'bg-green-500' : 'bg-red-500' }} h-6 rounded-full flex items-center justify-end pr-2 transition-all duration-500" style="width: {{ max($equityPercent, 5) }}%">
                                        @if($equityPercent > 20)
                                        <span class="text-xs text-white font-medium">{{ number_format($equityPercent, 0) }}%</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Analysis Summary -->
        @if(isset($changes['equity']))
        <div class="{{ $changes['equity']['improved'] ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' }} border rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    @if($changes['equity']['improved'])
                        <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    @else
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    @endif
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium {{ $changes['equity']['improved'] ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                        Financial Position {{ $changes['equity']['improved'] ? 'Strengthened' : 'Weakened' }}
                    </h3>
                    <p class="mt-1 text-sm {{ $changes['equity']['improved'] ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                        @if($changes['equity']['improved'])
                            Equity {{ $changes['equity']['difference'] >= 0 ? 'increased' : 'improved' }} by {{ number_format(abs($changes['equity']['difference']), 2) }} 
                            ({{ number_format(abs($changes['equity']['percentChange']), 1) }}%) compared to the previous period.
                        @else
                            Equity decreased by {{ number_format(abs($changes['equity']['difference']), 2) }} 
                            ({{ number_format(abs($changes['equity']['percentChange']), 1) }}%) compared to the previous period.
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
