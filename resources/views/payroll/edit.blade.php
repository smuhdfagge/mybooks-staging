<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Edit payroll {{ $payroll->payroll_number }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('payroll.update', $payroll) }}" method="POST" class="p-6" x-data="payslipForm()" @input="changed++">
                    @csrf
                    @method('PUT')

                    <!-- Employee Info (Read Only) -->
                    <div class="mb-8">
                        <h2 class="section-title mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg aria-hidden="true" class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Employee & Pay Period
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="employee_name" class="form-label">Employee</label>
                                <input type="text" id="employee_name" value="{{ $payroll->employee?->first_name }} {{ $payroll->employee?->last_name }}" readonly
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-400 shadow-sm bg-gray-100">
                            </div>

                            <div>
                                <x-field name="pay_period_start" label="Pay Period Start" type="date" :value="old('pay_period_start', $payroll->pay_period_start?->format('Y-m-d'))" required />
                            </div>

                            <div>
                                <x-field name="pay_period_end" label="Pay Period End" type="date" :value="old('pay_period_end', $payroll->pay_period_end?->format('Y-m-d'))" required />
                            </div>
                        </div>
                    </div>

                    <!-- Earnings -->
                    <div class="mb-8">
                        <h2 class="section-title mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg aria-hidden="true" class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Earnings
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-money-field name="basic_salary" label="Basic salary" :value="old('basic_salary', $payroll->basic_salary)" required />
                            </div>

                            <div>
                                <x-money-field name="allowances" label="Allowances" :value="old('allowances', $payroll->allowances)" />
                            </div>

                            <div>
                                <x-field name="overtime_hours" label="Overtime hours" type="number" :value="old('overtime_hours', (float) $payroll->overtime_hours)" step="0.01" min="0" inputmode="decimal" />
                            </div>

                            <div>
                                <x-money-field name="overtime_amount" label="Overtime pay" :value="old('overtime_amount', $payroll->overtime_amount)" />
                            </div>
                        </div>
                    </div>

                    <!-- Deductions -->
                    <div class="mb-8">
                        <h2 class="section-title mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg aria-hidden="true" class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Deductions
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-money-field name="tax_deduction" label="PAYE" :value="old('tax_deduction', $payroll->tax_deduction)"
                                    help="Changed the pay? Calculate it again from the tax table and this payslip's reliefs." />
                                <button type="submit" name="calculate_paye" value="1" class="btn-secondary mt-2" data-testid="calculate-paye">Calculate PAYE</button>
                            </div>

                            <div>
                                <x-money-field name="other_deductions" label="Other deductions" :value="old('other_deductions', $payroll->other_deductions)" />
                            </div>
                        </div>
                    </div>

                    <!-- What the employee gets, as the amounts are typed -->
                    <div class="mb-8 rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40" aria-live="polite" data-testid="payslip-totals">
                        <h2 class="sr-only">Totals</h2>
                        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                            <div><dt class="text-gray-600 dark:text-gray-400">Gross pay</dt><dd class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white" x-text="$money(gross)">{{ \App\Support\Money::format($payroll->gross_salary) }}</dd></div>
                            <div><dt class="text-gray-600 dark:text-gray-400">Deductions</dt><dd class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white" x-text="$money(deductions)">{{ \App\Support\Money::format($payroll->total_deductions) }}</dd></div>
                            <div><dt class="text-gray-600 dark:text-gray-400">Net pay</dt><dd class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white" x-text="$money(net)">{{ \App\Support\Money::format($payroll->net_salary) }}</dd></div>
                        </dl>
                    </div>

                    <!-- Notes -->
                    <div class="mb-8">
                        <h2 class="section-title mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg aria-hidden="true" class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Additional Information
                        </h2>
                        <div>
                            <x-field name="notes" label="Notes" type="textarea" :value="old('notes', $payroll->notes)" rows="3" />
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('payroll.show', $payroll) }}" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update payroll
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
