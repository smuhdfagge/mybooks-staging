<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Budget vs Actual: {{ $budget->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Fiscal Year {{ $budget->fiscal_year }}</p>
            </div>
            <a href="{{ route('budgets.show', $budget) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Budget
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- YTD Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">YTD Budget</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format($ytdUtilization['total_budget_ytd'], 2) }}
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">YTD Actual</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format($ytdUtilization['total_actual_ytd'], 2) }}
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Budget Utilization</div>
                    <div class="mt-1">
                        <span class="text-2xl font-semibold {{ $ytdUtilization['utilization_percent'] > 100 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            {{ $ytdUtilization['utilization_percent'] }}%
                        </span>
                    </div>
                    <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $ytdUtilization['utilization_percent'] > 100 ? 'bg-red-600' : 'bg-green-600' }}"
                             style="width: {{ min($ytdUtilization['utilization_percent'], 100) }}%"></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Remaining Budget</div>
                    <div class="mt-1 text-2xl font-semibold {{ $ytdUtilization['remaining'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ number_format($ytdUtilization['remaining'], 2) }}
                    </div>
                </div>
            </div>

            <!-- Over Budget Alerts -->
            @if($ytdUtilization['over_budget_count'] > 0)
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex">
                    <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-300">
                            {{ $ytdUtilization['over_budget_count'] }} Account(s) Over Budget
                        </h3>
                        <div class="mt-2 text-sm text-red-700 dark:text-red-400">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($ytdUtilization['over_budget_accounts'] as $item)
                                <li>{{ $item['account_name'] }}: {{ number_format(abs($item['variance']), 2) }} over budget ({{ abs($item['variance_percent']) }}%)</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Comparison Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Budget vs Actual by Account</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Budget</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Actual</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Variance</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Variance %</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @php
                                    $incomeComparison = $comparison->filter(fn($item) => $item['account_type'] === 'income');
                                    $expenseComparison = $comparison->filter(fn($item) => $item['account_type'] === 'expense');
                                @endphp

                                @if($incomeComparison->count() > 0)
                                <tr class="bg-green-50 dark:bg-green-900/20">
                                    <td colspan="6" class="px-4 py-2 text-sm font-semibold text-green-800 dark:text-green-300">Income Accounts</td>
                                </tr>
                                @foreach($incomeComparison as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">{{ $item['account_code'] }}</span>
                                        <span class="text-gray-900 dark:text-gray-100 ml-2">{{ $item['account_name'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($item['budgeted'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($item['actual'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $item['variance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $item['variance'] >= 0 ? '+' : '' }}{{ number_format($item['variance'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $item['variance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $item['variance'] >= 0 ? '+' : '' }}{{ number_format($item['variance_percent'], 1) }}%
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($item['is_over_budget'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                                Under Target
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                On Track
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                @endif

                                @if($expenseComparison->count() > 0)
                                <tr class="bg-red-50 dark:bg-red-900/20">
                                    <td colspan="6" class="px-4 py-2 text-sm font-semibold text-red-800 dark:text-red-300">Expense Accounts</td>
                                </tr>
                                @foreach($expenseComparison as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">{{ $item['account_code'] }}</span>
                                        <span class="text-gray-900 dark:text-gray-100 ml-2">{{ $item['account_name'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($item['budgeted'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($item['actual'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $item['variance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $item['variance'] >= 0 ? '+' : '' }}{{ number_format($item['variance'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $item['variance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $item['variance'] >= 0 ? '+' : '' }}{{ number_format($item['variance_percent'], 1) }}%
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($item['is_over_budget'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                                Over Budget
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                Under Budget
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                @endif

                                @if($comparison->count() === 0)
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No budget data available for comparison.
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                            <tfoot class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Total</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ number_format($comparison->sum('budgeted'), 2) }}
                                    </th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ number_format($comparison->sum('actual'), 2) }}
                                    </th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold {{ $comparison->sum('variance') >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $comparison->sum('variance') >= 0 ? '+' : '' }}{{ number_format($comparison->sum('variance'), 2) }}
                                    </th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="flex flex-wrap gap-6 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded bg-green-500"></span>
                        <span class="text-gray-600 dark:text-gray-400">Positive variance (under budget / above income target)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded bg-red-500"></span>
                        <span class="text-gray-600 dark:text-gray-400">Negative variance (over budget / below income target)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
