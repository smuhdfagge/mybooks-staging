<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Bank Disbursement Report') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="bank-disbursement" 
                    :filters="['start_date' => $startDate, 'end_date' => $endDate, 'status' => $status]" 
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
                <form method="GET" action="{{ route('reports.bank-disbursement') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <option value="">All Statuses</option>
                            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved (Ready to Pay)</option>
                            <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Disbursement</dt>
                                <dd class="text-lg font-semibold text-green-700 dark:text-green-400">{{ number_format($totals['total_net'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Total Payments</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $totals['count'] }}</dd>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Total Gross</dt>
                <dd class="mt-1 text-lg font-semibold text-brand-600 dark:text-brand-300">{{ number_format($totals['total_gross'], 2) }}</dd>
            </div>
        </div>

        <!-- By Payment Method -->
        @if($byPaymentMethod->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">By Payment Method</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach($byPaymentMethod as $method)
                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex justify-between items-center">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ str_replace('_', ' ', $method['method']) }}</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $method['count'] }} payments</p>
                            </div>
                            <span class="text-lg font-semibold text-green-700 dark:text-green-400">{{ number_format($method['total'], 2) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Disbursement Table -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Details</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payroll #</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee ID</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bank</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account #</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pay Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Net Amount</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($payrolls as $payroll)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->payroll_number }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->employee->first_name ?? '' }} {{ $payroll->employee->last_name ?? '' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $payroll->employee->employee_id ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $payroll->employee->bank_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        @if($payroll->employee->bank_account_number)
                                            ****{{ substr($payroll->employee->bank_account_number, -4) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $payroll->pay_date?->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 capitalize">{{ str_replace('_', ' ', $payroll->payment_method ?? 'N/A') }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-green-700 dark:text-green-400">{{ number_format($payroll->net_salary, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $payroll->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                            {{ $payroll->status === 'approved' ? 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200' : '' }}
                                            {{ $payroll->status === 'draft' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' : '' }}
                                        ">{{ ucfirst($payroll->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No payroll payments found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($payrolls->isNotEmpty())
                        <tfoot class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td colspan="7" class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-white">Total</td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-green-700 dark:text-green-400">{{ number_format($totals['total_net'], 2) }}</td>
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
                <p>Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }} | Bank account numbers are masked for security | Generated: {{ now()->format('M d, Y H:i') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
