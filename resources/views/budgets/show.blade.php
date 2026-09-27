<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $budget->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Fiscal Year {{ $budget->fiscal_year }}</p>
            </div>
            <div class="flex gap-2">
                @if($budget->isActive() || $budget->isLocked())
                <a href="{{ route('budgets.vs-actual', $budget) }}" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Budget vs Actual
                </a>
                @endif
                @can('edit budgets')
                @if(!$budget->isLocked())
                <a href="{{ route('budgets.edit', $budget) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                </a>
                @endif
                @endcan
                <a href="{{ route('budgets.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Status and Actions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                        @if($budget->isDraft())
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                Draft
                            </span>
                        @elseif($budget->isActive())
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                Locked
                            </span>
                        @endif
                    </div>
                    @can('edit budgets')
                    <div class="flex gap-2">
                        @if($budget->isDraft() && $budget->lines->count() > 0)
                        <form action="{{ route('budgets.activate', $budget) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
                                    onclick="return confirm('Activate this budget? Once active, budget amounts will be compared against actual transactions.')">
                                Activate Budget
                            </button>
                        </form>
                        @endif
                        @if($budget->isActive())
                        <form action="{{ route('budgets.lock', $budget) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700"
                                    onclick="return confirm('Lock this budget? This action cannot be undone and the budget will become read-only.')">
                                Lock Budget
                            </button>
                        </form>
                        @endif
                    </div>
                    @endcan
                </div>
                @if($budget->description)
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ $budget->description }}</p>
                @endif
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Income Budget</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">
                        {{ number_format($summary['income_budget'], 2) }}
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Expense Budget</div>
                    <div class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">
                        {{ number_format($summary['expense_budget'], 2) }}
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Net Budget (Income - Expense)</div>
                    <div class="mt-1 text-2xl font-semibold {{ $summary['net_budget'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ number_format($summary['net_budget'], 2) }}
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Budget Line Items</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $summary['accounts_count'] }}
                    </div>
                </div>
            </div>

            <!-- YTD Utilization (if active) -->
            @if($ytdUtilization)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Year-to-Date Budget Utilization</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">YTD Budget</div>
                        <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($ytdUtilization['total_budget_ytd'], 2) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">YTD Actual</div>
                        <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($ytdUtilization['total_actual_ytd'], 2) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Utilization</div>
                        <div class="mt-1 text-xl font-semibold {{ $ytdUtilization['utilization_percent'] > 100 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $ytdUtilization['utilization_percent'] }}%
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Accounts Over Budget</div>
                        <div class="mt-1 text-xl font-semibold {{ $ytdUtilization['over_budget_count'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $ytdUtilization['over_budget_count'] }}
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Budget Lines Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Budget Line Items</h3>
                    
                    @if($budget->lines->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                                    @foreach($months as $key => $name)
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ substr($name, 0, 3) }}</th>
                                    @endforeach
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider bg-gray-100 dark:bg-gray-600">Annual</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @php
                                    $incomeLines = $budget->lines->filter(fn($l) => $l->account->type === 'income');
                                    $expenseLines = $budget->lines->filter(fn($l) => $l->account->type === 'expense');
                                @endphp
                                
                                @if($incomeLines->count() > 0)
                                <tr class="bg-green-50 dark:bg-green-900/20">
                                    <td colspan="{{ count($months) + 2 }}" class="px-4 py-2 text-sm font-semibold text-green-800 dark:text-green-300">Income Accounts</td>
                                </tr>
                                @foreach($incomeLines as $line)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">{{ $line->account->account_code }}</span>
                                        <span class="text-gray-900 dark:text-gray-100 ml-2">{{ $line->account->name }}</span>
                                    </td>
                                    @foreach($months as $key => $name)
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($line->{$key}, 2) }}
                                    </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700">
                                        {{ number_format($line->annual_total, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                                @endif

                                @if($expenseLines->count() > 0)
                                <tr class="bg-red-50 dark:bg-red-900/20">
                                    <td colspan="{{ count($months) + 2 }}" class="px-4 py-2 text-sm font-semibold text-red-800 dark:text-red-300">Expense Accounts</td>
                                </tr>
                                @foreach($expenseLines as $line)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">{{ $line->account->account_code }}</span>
                                        <span class="text-gray-900 dark:text-gray-100 ml-2">{{ $line->account->name }}</span>
                                    </td>
                                    @foreach($months as $key => $name)
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($line->{$key}, 2) }}
                                    </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700">
                                        {{ number_format($line->annual_total, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No budget line items yet.
                        @can('edit budgets')
                        @if(!$budget->isLocked())
                        <a href="{{ route('budgets.edit', $budget) }}" class="text-blue-600 dark:text-blue-400 hover:underline">Add budget lines</a>
                        @endif
                        @endcan
                    </div>
                    @endif
                </div>
            </div>

            <!-- Meta Info -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Created By</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $budget->createdBy?->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Created At</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $budget->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    @if($budget->approvedBy)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Approved By</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $budget->approvedBy->name }} on {{ $budget->approved_at->format('M d, Y') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
