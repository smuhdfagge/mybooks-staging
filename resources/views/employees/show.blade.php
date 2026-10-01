<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                @if($employee->photo_path)
                    <img src="{{ asset('storage/' . $employee->photo_path) }}" alt="{{ $employee->full_name }}" class="h-14 w-14 rounded-full object-cover ring-2 ring-indigo-500/20">
                @else
                    <div class="h-14 w-14 rounded-full bg-indigo-600 flex items-center justify-center ring-2 ring-indigo-500/20">
                        <span class="text-lg font-bold text-white">{{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) }}</span>
                    </div>
                @endif
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ $employee->full_name }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $employee->employee_id }} · {{ $employee->designation?->name ?? 'No Designation' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('employees.edit', $employee) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
                <a href="{{ route('employees.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
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
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Department</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $employee->department?->name ?? 'Unassigned' }}</p>
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
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Salary Structure</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $employee->salaryStructure->name ?? 'Not Assigned' }}</p>
                            @if($employee->salaryStructure)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Net: {{ number_format($employee->salaryStructure->net_salary, 2) }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Hire Date</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $employee->hire_date?->format('M d, Y') ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Employment Type</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ ucfirst(str_replace('-', ' ', $employee->employment_type ?? 'N/A')) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Employee Details -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Personal Information</h3>
                            <dl class="space-y-4">
                                @if($employee->email)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="mailto:{{ $employee->email }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">{{ $employee->email }}</a>
                                    </dd>
                                </div>
                                @endif

                                @if($employee->phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="tel:{{ $employee->phone }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">{{ $employee->phone }}</a>
                                    </dd>
                                </div>
                                @endif

                                @if($employee->date_of_birth)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date of Birth</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $employee->date_of_birth->format('M d, Y') }}</dd>
                                </div>
                                @endif

                                @if($employee->gender)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Gender</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ ucfirst($employee->gender) }}</dd>
                                </div>
                                @endif

                                @if($employee->marital_status)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Marital Status</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ ucfirst($employee->marital_status) }}</dd>
                                </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="mt-1">
                                        @if($employee->status === 'active')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400">Active</span>
                                        @elseif($employee->status === 'on_leave')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-400">On Leave</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-400">{{ ucfirst($employee->status ?? 'Inactive') }}</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    @if($employee->address || $employee->city || $employee->state || $employee->country)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Address</h3>
                            <dl class="space-y-4">
                                <div>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">
                                        @if($employee->address){{ $employee->address }}<br>@endif
                                        @if($employee->city || $employee->state || $employee->postal_code)
                                            {{ $employee->city }}{{ $employee->city && $employee->state ? ', ' : '' }}{{ $employee->state }} {{ $employee->postal_code }}<br>
                                        @endif
                                        {{ $employee->country }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    @endif

                    @if($employee->emergency_contact_name || $employee->emergency_contact_phone)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Emergency Contact</h3>
                            <dl class="space-y-4">
                                @if($employee->emergency_contact_name)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $employee->emergency_contact_name }}</dd>
                                </div>
                                @endif
                                @if($employee->emergency_contact_phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="tel:{{ $employee->emergency_contact_phone }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">{{ $employee->emergency_contact_phone }}</a>
                                    </dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                    @endif

                    @if($employee->notes)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Notes</h3>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $employee->notes }}</p>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Right Column -->
                <div class="lg:col-span-2">
                    <!-- Banking Information -->
                    @if($employee->bank_name || $employee->bank_account_number || $employee->tax_id || $employee->tax_state_id || $employee->pension_fund_administrator_id || $employee->rsa_pin || $employee->nhf_number)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Banking & Tax Information</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @if($employee->bank_name)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $employee->bank_name }}</dd>
                                </div>
                                @endif
                                @if($employee->bank_account_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">****{{ substr($employee->bank_account_number, -4) }}</dd>
                                </div>
                                @endif
                                @if($employee->bank_routing_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Routing Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $employee->bank_routing_number }}</dd>
                                </div>
                                @endif
                                @if($employee->tax_id)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">****{{ substr($employee->tax_id, -4) }}</dd>
                                </div>
                                @endif
                                @if($employee->taxState)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">PAYE State</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $employee->taxState->name }}</dd>
                                </div>
                                @endif
                                @if($employee->pensionFundAdministrator)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Pension Fund Administrator</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $employee->pensionFundAdministrator->name }}</dd>
                                </div>
                                @endif
                                @if($employee->rsa_pin)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">RSA PIN</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">****{{ substr($employee->rsa_pin, -4) }}</dd>
                                </div>
                                @endif
                                @if($employee->nhf_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">NHF Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">****{{ substr($employee->nhf_number, -4) }}</dd>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Leave Records -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Leave Records</h3>
                            
                            @if($employee->leaves && $employee->leaves->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">From</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">To</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($employee->leaves->take(5) as $leave)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $leave->leaveType?->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $leave->start_date?->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $leave->end_date?->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @php
                                                    $statusColors = [
                                                        'pending' => 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-400',
                                                        'approved' => 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400',
                                                        'rejected' => 'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-400',
                                                    ];
                                                @endphp
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$leave->status] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300' }}">
                                                    {{ ucfirst($leave->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No leave records found.</p>
                            @endif
                        </div>
                    </div>

                    <!-- Payroll Records -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Payroll Records</h3>
                            
                            @if($employee->payrolls && $employee->payrolls->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Period</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Gross</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deductions</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Net Pay</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($employee->payrolls->take(5) as $payroll)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $payroll->pay_period_start?->format('M d') }} - {{ $payroll->pay_period_end?->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-right">
                                                {{ number_format($payroll->gross_salary ?? 0, 2) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-red-600 dark:text-red-400 text-right">
                                                -{{ number_format($payroll->total_deductions ?? 0, 2) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-green-600 dark:text-green-400 text-right font-medium">
                                                {{ number_format($payroll->net_salary ?? 0, 2) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @php
                                                    $payrollStatusColors = [
                                                        'pending' => 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-400',
                                                        'processed' => 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-400',
                                                        'paid' => 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400',
                                                    ];
                                                @endphp
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $payrollStatusColors[$payroll->status] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300' }}">
                                                    {{ ucfirst($payroll->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No payroll records found.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
