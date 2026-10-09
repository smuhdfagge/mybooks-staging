<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $payroll->payroll_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $payroll->employee?->first_name }} {{ $payroll->employee?->last_name }} · {{ $payroll->pay_period_start?->format('M d') }} - {{ $payroll->pay_period_end?->format('M d, Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($payroll->status === 'draft')
                    <form action="{{ route('payroll.approve', $payroll) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Approve
                        </button>
                    </form>
                    <a href="{{ route('payroll.edit', $payroll) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                @endif
                @if($payroll->status === 'approved')
                    <form action="{{ route('payroll.mark-paid', $payroll) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Mark as Paid
                        </button>
                    </form>
                @endif
                <a href="{{ route('payroll.payslip', $payroll) }}" class="btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Payslip
                </a>
                <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Status Badge -->
            <div class="mb-6">
                <span class="px-4 py-2 inline-flex text-sm leading-5 font-semibold rounded-full 
                    {{ $payroll->status === 'draft' ? 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100' : '' }}
                    {{ $payroll->status === 'approved' ? 'bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100' : '' }}
                    {{ $payroll->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : '' }}">
                    Status: {{ ucfirst($payroll->status) }}
                </span>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-brand-100 dark:bg-brand-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Employee</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $payroll->employee?->first_name }} {{ $payroll->employee?->last_name }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gross Pay</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($payroll->gross_salary ?? $payroll->gross_pay ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 dark:bg-red-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Deductions</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($payroll->total_deductions ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-brand-100 dark:bg-brand-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Net Pay</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($payroll->net_salary ?? $payroll->net_pay ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Employee Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Employee Details</h3>
                        <dl class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Employee ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->employee?->employee_id ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Department</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->employee?->department?->name ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Designation</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->employee?->designation?->title ?? $payroll->employee?->designation?->name ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->employee?->email ?? '-' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Pay Period Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Pay Period Details</h3>
                        <dl class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Pay Period Start</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->pay_period_start?->format('M d, Y') ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Pay Period End</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->pay_period_end?->format('M d, Y') ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Pay Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->pay_date?->format('M d, Y') ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payroll Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $payroll->payroll_number }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Earnings Breakdown -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Earnings Breakdown</h3>
                        <dl class="space-y-3">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Basic Salary</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($payroll->basic_salary ?? 0, 2) }}</dd>
                            </div>
                            @if(!empty($payroll->allowance_details))
                                @foreach($payroll->allowance_details as $allowance)
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $allowance['name'] }}
                                            @if(($allowance['amount_type'] ?? '') === 'percentage')
                                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $allowance['rate'] }}%)</span>
                                            @endif
                                        </dt>
                                        <dd class="text-sm font-medium text-green-600 dark:text-green-400">+{{ number_format($allowance['amount'], 2) }}</dd>
                                    </div>
                                @endforeach
                            @else
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Allowances</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($payroll->allowances ?? 0, 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Overtime ({{ $payroll->overtime_hours ?? 0 }} hrs)</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($payroll->overtime_amount ?? 0, 2) }}</dd>
                            </div>
                            <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-900 dark:text-gray-100">Gross Salary</dt>
                                <dd class="text-sm font-bold text-green-600 dark:text-green-400">{{ number_format($payroll->gross_salary ?? 0, 2) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Deductions Breakdown -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Deductions Breakdown</h3>
                        <dl class="space-y-3">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Tax Deduction</dt>
                                <dd class="text-sm font-medium text-red-600 dark:text-red-400">{{ number_format($payroll->tax_deduction ?? 0, 2) }}</dd>
                            </div>
                            @if(!empty($payroll->deduction_details))
                                @foreach($payroll->deduction_details as $deduction)
                                    @if(str_starts_with($deduction['name'] ?? '', '_'))
                                        @continue
                                    @endif
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $deduction['name'] }}
                                            @if(($deduction['amount_type'] ?? '') === 'percentage')
                                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $deduction['rate'] }}%)</span>
                                            @endif
                                        </dt>
                                        <dd class="text-sm font-medium text-red-600 dark:text-red-400">-{{ number_format($deduction['amount'], 2) }}</dd>
                                    </div>
                                @endforeach
                            @else
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Other Deductions</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($payroll->other_deductions ?? 0, 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-900 dark:text-gray-100">Total Deductions</dt>
                                <dd class="text-sm font-bold text-red-600 dark:text-red-400">{{ number_format($payroll->total_deductions ?? 0, 2) }}</dd>
                            </div>
                            <div class="flex justify-between pt-3 border-t-2 border-gray-300 dark:border-gray-600">
                                <dt class="text-base font-bold text-gray-900 dark:text-gray-100">Net Salary</dt>
                                <dd class="text-base font-bold text-brand-600 dark:text-brand-300">{{ number_format($payroll->net_salary ?? 0, 2) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            @if($payroll->notes)
            <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Notes</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $payroll->notes }}</p>
                </div>
            </div>
            @endif

            <!-- Delete Button -->
            @if($payroll->status === 'draft')
            <div class="mt-6 flex justify-end">
                <form action="{{ route('payroll.destroy', $payroll) }}" method="POST" data-confirm="Are you sure you want to delete this payroll record?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete Payroll
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
