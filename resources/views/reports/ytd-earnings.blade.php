<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Year-to-Date Earnings') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="ytd-earnings" 
                    :filters="['year' => $year, 'employee_id' => $employeeId]" 
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
                <form method="GET" action="{{ route('reports.ytd-earnings') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Year</label>
                        <select name="year" id="year" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            @for($y = now()->year; $y >= now()->year - 5; $y--)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label for="employee_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Employee</label>
                        <select name="employee_id" id="employee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <option value="">All Employees</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ $employeeId == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->first_name }} {{ $employee->last_name }}
                                </option>
                            @endforeach
                        </select>
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

        <!-- Grand Totals -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">YTD Gross</dt>
                <dd class="mt-1 text-lg font-semibold text-brand-600 dark:text-brand-300">{{ number_format($grandTotals['gross'], 2) }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">YTD Tax</dt>
                <dd class="mt-1 text-lg font-semibold text-yellow-600 dark:text-yellow-400">{{ number_format($grandTotals['tax'], 2) }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">YTD Deductions</dt>
                <dd class="mt-1 text-lg font-semibold text-red-600 dark:text-red-400">{{ number_format($grandTotals['deductions'], 2) }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">YTD Net</dt>
                <dd class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">{{ number_format($grandTotals['net'], 2) }}</dd>
            </div>
        </div>

        <!-- YTD Summary Table -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Year-to-Date Summary — {{ $year }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Periods</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Basic</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Allowances</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Overtime</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Gross</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Tax</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Deductions</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Net</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($byEmployee as $record)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $record['employee']->first_name }} {{ $record['employee']->last_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $record['employee']->department->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-gray-100">{{ $record['pay_periods'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($record['ytd_basic'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($record['ytd_allowances'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($record['ytd_overtime'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($record['ytd_gross'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-yellow-600 dark:text-yellow-400">{{ number_format($record['ytd_tax'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ number_format($record['ytd_total_deductions'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-green-600 dark:text-green-400">{{ number_format($record['ytd_net'], 2) }}</td>
                                </tr>
                                @if($record['monthly_breakdown']->count() > 1)
                                <tr class="bg-gray-50/50 dark:bg-gray-900/30">
                                    <td colspan="10" class="px-4 py-2">
                                        <details class="text-xs">
                                            <summary class="cursor-pointer text-brand-600 dark:text-brand-300 hover:underline">Monthly Breakdown ({{ $record['monthly_breakdown']->count() }} months)</summary>
                                            <div class="mt-2 overflow-x-auto">
                                                <table class="min-w-full text-xs">
                                                    <thead>
                                                        <tr class="text-gray-500 dark:text-gray-400">
                                                            <th class="px-2 py-1 text-left">Month</th>
                                                            <th class="px-2 py-1 text-right">Basic</th>
                                                            <th class="px-2 py-1 text-right">Allowances</th>
                                                            <th class="px-2 py-1 text-right">Overtime</th>
                                                            <th class="px-2 py-1 text-right">Gross</th>
                                                            <th class="px-2 py-1 text-right">Tax</th>
                                                            <th class="px-2 py-1 text-right">Net</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($record['monthly_breakdown'] as $month => $data)
                                                        <tr>
                                                            <td class="px-2 py-1 text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($month . '-01')->format('M Y') }}</td>
                                                            <td class="px-2 py-1 text-right text-gray-700 dark:text-gray-300">{{ number_format($data['basic_salary'], 2) }}</td>
                                                            <td class="px-2 py-1 text-right text-gray-700 dark:text-gray-300">{{ number_format($data['allowances'], 2) }}</td>
                                                            <td class="px-2 py-1 text-right text-gray-700 dark:text-gray-300">{{ number_format($data['overtime'], 2) }}</td>
                                                            <td class="px-2 py-1 text-right text-gray-700 dark:text-gray-300">{{ number_format($data['gross'], 2) }}</td>
                                                            <td class="px-2 py-1 text-right text-gray-700 dark:text-gray-300">{{ number_format($data['tax'], 2) }}</td>
                                                            <td class="px-2 py-1 text-right text-gray-700 dark:text-gray-300">{{ number_format($data['net'], 2) }}</td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No payroll records found for {{ $year }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($byEmployee->isNotEmpty())
                        <tfoot class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-white">Grand Totals</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($grandTotals['basic'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($grandTotals['allowances'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($grandTotals['overtime'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($grandTotals['gross'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($grandTotals['tax'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-red-600 dark:text-red-400">{{ number_format($grandTotals['deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-green-600 dark:text-green-400">{{ number_format($grandTotals['net'], 2) }}</td>
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
                <p>Year: {{ $year }} | Only approved and paid payrolls included | Generated: {{ now()->format('M d, Y H:i') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
