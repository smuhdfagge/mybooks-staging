<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Bulk Create Payroll') }}
            </h2>
            <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('payroll.bulk-store') }}" method="POST" class="p-6">
                    @csrf

                    <!-- Pay Period -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Pay Period
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-field name="pay_period_start" label="Pay Period Start" type="date" :value="old('pay_period_start')" required />
                            </div>

                            <div>
                                <x-field name="pay_period_end" label="Pay Period End" type="date" :value="old('pay_period_end')" required />
                            </div>
                        </div>
                    </div>

                    <!-- Employee Selection -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Select Employees
                        </h3>
                        
                        <div class="mb-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" id="select-all" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Select All Active Employees</span>
                            </label>
                        </div>

                        @error('employee_ids')
                            <p class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto p-2 border border-gray-200 dark:border-gray-700 rounded-md">
                            @forelse($employees as $employee)
                                <label class="flex items-start p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" 
                                        {{ in_array($employee->id, old('employee_ids', [])) ? 'checked' : '' }}
                                        class="employee-checkbox rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 mt-1">
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $employee->first_name }} {{ $employee->last_name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $employee->employee_id }} · {{ $employee->department?->name ?? 'No Dept' }}</p>
                                        <p class="text-xs text-green-600 dark:text-green-400">{{ number_format($employee->salary ?? 0, 2) }} / {{ $employee->salary_type ?? 'month' }}</p>
                                    </div>
                                </label>
                            @empty
                                <div class="col-span-full text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No active employees found.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Info Note -->
                    <div class="mb-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex">
                            <svg class="w-5 h-5 text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300">Bulk Payroll Creation</h4>
                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">
                                    Payroll records will be created for each selected employee using their base salary. 
                                    You can edit individual payroll records to add allowances, overtime, bonuses, and deductions after creation.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Create Payroll Records
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script nonce="{{ app('csp-nonce') }}">
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.employee-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const selectAll = document.getElementById('select-all');
                const checkboxes = document.querySelectorAll('.employee-checkbox');
                const checkedCount = document.querySelectorAll('.employee-checkbox:checked').length;
                selectAll.checked = checkedCount === checkboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            });
        });
    </script>
</x-app-layout>
