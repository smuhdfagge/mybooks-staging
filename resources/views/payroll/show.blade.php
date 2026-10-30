<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="'Payslip '.$payroll->payroll_number" :description="trim(($payroll->employee?->first_name ?? '').' '.($payroll->employee?->last_name ?? '')).' · '.$payroll->pay_period_start?->format('j M').' – '.$payroll->pay_period_end?->format('j M Y')">
            <x-slot name="badge"><x-status-badge :status="$payroll->status" /></x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('payroll.payslip', $payroll)">Download payslip</x-table.menu-item>
                @if ($payroll->status === 'draft')
                    @can('delete payroll')
                        <x-table.menu-item danger :post="route('payroll.destroy', $payroll)" method="DELETE" confirm="Delete this payroll record?">Delete</x-table.menu-item>
                    @endcan
                @endif
            </x-slot>
            <x-slot name="actions">
                @if ($payroll->status === 'draft')
                    <a href="{{ route('payroll.edit', $payroll) }}" class="btn-secondary">Edit</a>
                    <form action="{{ route('payroll.approve', $payroll) }}" method="POST">@csrf
                        <button type="submit" class="btn-new">Approve</button>
                    </form>
                @elseif ($payroll->status === 'approved')
                    <form action="{{ route('payroll.mark-paid', $payroll) }}" method="POST" class="flex items-center gap-2">@csrf
                        @include('payroll._paid-from')
                        <button type="submit" class="btn-new">Mark as paid</button>
                    </form>
                @endif
            </x-slot>
        </x-table.page-header>
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
                            <svg class="w-6 h-6 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            <svg class="w-6 h-6 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <dl class="grid grid-cols-2 gap-4">
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
                        </dl>
                    </div>
                </div>

                <!-- Pay Period Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Pay Period Details</h3>
                        <dl class="grid grid-cols-2 gap-4">
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
                                        <dd class="text-sm font-medium text-green-700 dark:text-green-400">+{{ number_format($allowance['amount'], 2) }}</dd>
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
                                <dd class="text-sm font-bold text-green-700 dark:text-green-400">{{ number_format($payroll->gross_salary ?? 0, 2) }}</dd>
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
                                <dd class="text-sm font-medium text-red-600 dark:text-red-300">{{ number_format($payroll->tax_deduction ?? 0, 2) }}</dd>
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
                                        <dd class="text-sm font-medium text-red-600 dark:text-red-300">-{{ number_format($deduction['amount'], 2) }}</dd>
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
                                <dd class="text-sm font-bold text-red-600 dark:text-red-300">{{ number_format($payroll->total_deductions ?? 0, 2) }}</dd>
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

        </div>
    </div>
</x-app-layout>
