<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Employee Earnings') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="employee-earnings" 
                    :filters="['start_date' => $startDate, 'end_date' => $endDate, 'employee_id' => $employeeId]" 
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
                <form method="GET" action="{{ route('reports.employee-earnings') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Gross Salary</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($totalGross, 2) }}</dd>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Allowances</dt>
                    <dd class="mt-1 text-lg font-semibold text-brand-600 dark:text-brand-300">{{ number_format($totalAllowances, 2) }}</dd>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Overtime</dt>
                    <dd class="mt-1 text-lg font-semibold text-orange-600 dark:text-orange-400">{{ number_format($totalOvertime, 2) }}</dd>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Tax</dt>
                    <dd class="mt-1 text-lg font-semibold text-yellow-600 dark:text-yellow-400">{{ number_format($totalTax, 2) }}</dd>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Total Deductions</dt>
                    <dd class="mt-1 text-lg font-semibold text-red-600 dark:text-red-400">{{ number_format($totalDeductions, 2) }}</dd>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Net Salary</dt>
                    <dd class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">{{ number_format($totalNet, 2) }}</dd>
                </div>
            </div>
        </div>

        <!-- Earnings Detail Table -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Earnings Details</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pay Date</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Basic</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Allowances</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Overtime</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Gross</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Deductions</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Net</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($payrolls as $payroll)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-gray-500">
                                                    <span class="text-xs font-medium leading-none text-white">
                                                        {{ strtoupper(substr($payroll->employee->first_name ?? '', 0, 1)) }}{{ strtoupper(substr($payroll->employee->last_name ?? '', 0, 1)) }}
                                                    </span>
                                                </span>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $payroll->employee->first_name ?? '' }} {{ $payroll->employee->last_name ?? '' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                        {{ $payroll->employee->department->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                        {{ $payroll->pay_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                        {{ number_format($payroll->basic_salary, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-brand-600 dark:text-brand-300">
                                        {{ number_format($payroll->allowances, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-orange-600 dark:text-orange-400">
                                        {{ number_format($payroll->overtime_amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                        {{ number_format($payroll->gross_salary, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-yellow-600 dark:text-yellow-400">
                                        {{ number_format($payroll->tax_deduction, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600 dark:text-red-400">
                                        {{ number_format($payroll->total_deductions, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-green-600 dark:text-green-400">
                                        {{ number_format($payroll->net_salary, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        No earnings records found for the selected period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($payrolls->count() > 0)
                            <tfoot class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <td class="px-6 py-3 text-left text-sm font-bold text-gray-900 dark:text-white" colspan="3">Totals</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ number_format($payrolls->sum('basic_salary'), 2) }}</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-brand-600 dark:text-brand-300">{{ number_format($totalAllowances, 2) }}</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-orange-600 dark:text-orange-400">{{ number_format($totalOvertime, 2) }}</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ number_format($totalGross, 2) }}</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($totalTax, 2) }}</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-red-600 dark:text-red-400">{{ number_format($totalDeductions, 2) }}</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-green-600 dark:text-green-400">{{ number_format($totalNet, 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
