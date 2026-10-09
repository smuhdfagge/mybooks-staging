<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Payroll Register') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="payroll-register" 
                    :filters="['month' => $month, 'status' => $status, 'department_id' => $departmentId]" 
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
                <form method="GET" action="{{ route('reports.payroll-register') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label for="month" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Month</label>
                        <input type="month" name="month" id="month" value="{{ $month }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>
                    <div>
                        <label for="department_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        <select name="department_id" id="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ $departmentId == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <option value="">All Statuses</option>
                            <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
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

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Employees</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $payrolls->unique('employee_id')->count() }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Gross Salary</dt>
                <dd class="mt-1 text-lg font-semibold text-brand-600 dark:text-brand-300">{{ number_format($totals['gross_salary'], 2) }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Total Deductions</dt>
                <dd class="mt-1 text-lg font-semibold text-red-600 dark:text-red-300">{{ number_format($totals['total_deductions'], 2) }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Net Salary</dt>
                <dd class="mt-1 text-lg font-semibold text-green-700 dark:text-green-400">{{ number_format($totals['net_salary'], 2) }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Employer Cost</dt>
                <dd class="mt-1 text-lg font-semibold text-accent-700 dark:text-accent-300">{{ number_format($totals['employer_contributions'], 2) }}</dd>
            </div>
        </div>

        <!-- Status Breakdown -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Status Breakdown</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <span class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ $statusCounts['draft'] }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Draft</p>
                    </div>
                    <div class="text-center p-3 bg-brand-50 dark:bg-brand-900/20 rounded-lg">
                        <span class="text-2xl font-bold text-brand-600 dark:text-brand-300">{{ $statusCounts['approved'] }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Approved</p>
                    </div>
                    <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <span class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $statusCounts['paid'] }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Paid</p>
                    </div>
                    <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <span class="text-2xl font-bold text-red-600 dark:text-red-300">{{ $statusCounts['cancelled'] }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Cancelled</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Register Table -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payroll Register — {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payroll #</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Department</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Basic</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Allowances</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Overtime</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Gross</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Other Ded.</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Net</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employer</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($payrolls as $payroll)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->payroll_number }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->employee->first_name ?? '' }} {{ $payroll->employee->last_name ?? '' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $payroll->employee->department->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($payroll->basic_salary, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($payroll->allowances, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($payroll->overtime_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($payroll->gross_salary, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-300">{{ number_format($payroll->tax_deduction, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-300">{{ number_format($payroll->other_deductions, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($payroll->net_salary, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-accent-700 dark:text-accent-300">{{ number_format($payroll->employer_contributions, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $payroll->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                            {{ $payroll->status === 'approved' ? 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200' : '' }}
                                            {{ $payroll->status === 'draft' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' : '' }}
                                            {{ $payroll->status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                        ">{{ ucfirst($payroll->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No payroll records found for the selected period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($payrolls->isNotEmpty())
                        <tfoot class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-white">Totals</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($totals['basic_salary'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($totals['allowances'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($totals['overtime_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-white">{{ number_format($totals['gross_salary'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-red-600 dark:text-red-300">{{ number_format($totals['tax_deduction'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-red-600 dark:text-red-300">{{ number_format($totals['other_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-green-700 dark:text-green-400">{{ number_format($totals['net_salary'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-accent-700 dark:text-accent-300">{{ number_format($totals['employer_contributions'], 2) }}</td>
                                <td></td>
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
                <p>Period: {{ $startDate->format('M d, Y') }} — {{ $endDate->format('M d, Y') }} | Generated: {{ now()->format('M d, Y H:i') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
