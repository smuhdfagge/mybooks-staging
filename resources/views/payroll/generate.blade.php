<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Generate Payroll') }}
            </h2>
            <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Payroll
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if($employeesWithStructures->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="text-center py-12 px-6">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No salary structures found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You need to set up salary structures for employees before generating payroll.</p>
                        <div class="mt-6">
                            <a href="{{ route('salary-structures.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700">
                                Setup Salary Structure
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <form action="{{ route('payroll.generate') }}" method="POST" class="p-6" x-data="generatePayrollForm()">
                        @csrf

                        <!-- Period & Settings -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Payroll Period & Settings
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <x-field name="month" label="Month" type="month" :value="old('month', $currentMonth)" required />
                                </div>
                                <div>
                                    <x-field name="tax_rate" label="Tax Rate (%)" type="number" :value="old('tax_rate', 0)" step="0.01" min="0" max="100" placeholder="Enter tax percentage" />
                                </div>
                            </div>
                        </div>

                        <!-- Employee Selection -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Select Employees ({{ $employeesWithStructures->count() }} with salary structures)
                            </h3>

                            <div class="mb-4">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" @click="toggleAll()" :checked="allSelected" class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Select All Employees</span>
                                </label>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-10"></th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Employee</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Department</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Basic Salary</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Allowances</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Deductions</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Gross</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Net</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($employeesWithStructures as $employee)
                                            @php
                                                $structure = $employee->salaryStructure;
                                                $totalAllow = $structure ? $structure->total_allowances : 0;
                                                $totalDeduct = $structure ? $structure->total_deductions : 0;
                                                $gross = $structure ? $structure->gross_salary : 0;
                                                $net = $structure ? $structure->net_salary : 0;
                                            @endphp
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-3">
                                                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                                        class="employee-checkbox rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300"
                                                        @click="updateSelectAll()"
                                                        {{ in_array($employee->id, old('employee_ids', [])) ? 'checked' : '' }}>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $employee->employee_id }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $employee->department->name ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">
                                                    {{ number_format($structure->basic_salary ?? 0, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right text-green-700 dark:text-green-400">
                                                    +{{ number_format($totalAllow, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-300">
                                                    -{{ number_format($totalDeduct, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                                                    {{ number_format($gross, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right font-bold text-gray-900 dark:text-gray-100">
                                                    {{ number_format($net, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Generate Payroll
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function generatePayrollForm() {
            return {
                allSelected: false,
                toggleAll() {
                    this.allSelected = !this.allSelected;
                    document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = this.allSelected);
                },
                updateSelectAll() {
                    const checkboxes = document.querySelectorAll('.employee-checkbox');
                    this.allSelected = Array.from(checkboxes).every(cb => cb.checked);
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
