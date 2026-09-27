<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Tax Liability — Payroll') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="tax-liability-payroll" 
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
                <form method="GET" action="{{ route('reports.tax-liability-payroll') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Tax Liability</dt>
                                <dd class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">{{ number_format($totals['total_tax'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Total Taxable Income</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($totals['total_taxable'], 2) }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Effective Tax Rate</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $totals['effective_rate'] }}%</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Employees</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $totals['employee_count'] }}</dd>
            </div>
        </div>

        <!-- Monthly Breakdown -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Monthly Tax Summary</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Month</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employees</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taxable Income</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax Deducted</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Effective Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($monthlyBreakdown as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-3 text-sm text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($row['month'] . '-01')->format('F Y') }}</td>
                                    <td class="px-6 py-3 text-sm text-center text-gray-900 dark:text-gray-100">{{ $row['employee_count'] }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($row['total_taxable'], 2) }}</td>
                                    <td class="px-6 py-3 text-sm text-right font-medium text-yellow-600 dark:text-yellow-400">{{ number_format($row['total_tax'], 2) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ $row['effective_rate'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No tax records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- By Department -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Tax by Department</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employees</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taxable Income</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax Deducted</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($byDepartment as $dept)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $dept['department_name'] }}</td>
                                    <td class="px-6 py-3 text-sm text-center text-gray-900 dark:text-gray-100">{{ $dept['employee_count'] }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($dept['total_taxable'], 2) }}</td>
                                    <td class="px-6 py-3 text-sm text-right font-medium text-yellow-600 dark:text-yellow-400">{{ number_format($dept['total_tax'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Per-Employee Detail -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Employee Tax Detail</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pay Periods</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taxable Income</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax Deducted</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Effective Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($byEmployee as $record)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $record['employee']->first_name }} {{ $record['employee']->last_name }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $record['employee']->department->name ?? 'N/A' }}</td>
                                    <td class="px-6 py-3 text-sm text-center text-gray-900 dark:text-gray-100">{{ $record['pay_periods'] }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($record['taxable_income'], 2) }}</td>
                                    <td class="px-6 py-3 text-sm text-right font-medium text-yellow-600 dark:text-yellow-400">{{ number_format($record['tax_deducted'], 2) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ $record['effective_rate'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No tax records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($byEmployee->isNotEmpty())
                        <tfoot class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td colspan="3" class="px-6 py-3 text-sm font-bold text-gray-900 dark:text-white">Totals</td>
                                <td class="px-6 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($totals['total_taxable'], 2) }}</td>
                                <td class="px-6 py-3 text-sm text-right font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($totals['total_tax'], 2) }}</td>
                                <td class="px-6 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ $totals['effective_rate'] }}%</td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Report Info -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-4 text-xs text-gray-500 dark:text-gray-400">
                <p>Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }} | Only approved/paid payrolls | Generated: {{ now()->format('M d, Y H:i') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
