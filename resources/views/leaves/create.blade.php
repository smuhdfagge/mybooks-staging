<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Request leave" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('leaves.store') }}" method="POST" class="p-6">
                    @csrf

                    <div class="space-y-6">
                        <!-- Employee -->
                        <div>
                            <label for="employee_id" class="form-label">Employee <span class="text-red-600 dark:text-red-300">*</span></label>
                            <select name="employee_id" id="employee_id" required
                                class="form-control @error('employee_id') border-red-500 @enderror" @error('employee_id') aria-invalid="true" aria-describedby="employee_id-error" @enderror>
                                <option value="">Select Employee</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id', request('employee_id')) == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->first_name }} {{ $employee->last_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')
                                <p id="employee_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Leave Type -->
                        <div>
                            <label for="leave_type_id" class="form-label">Leave Type <span class="text-red-600 dark:text-red-300">*</span></label>
                            <select name="leave_type_id" id="leave_type_id" required
                                class="form-control @error('leave_type_id') border-red-500 @enderror" @error('leave_type_id') aria-invalid="true" aria-describedby="leave_type_id-error" @enderror>
                                <option value="">Select Leave Type</option>
                                @foreach($leaveTypes as $leaveType)
                                    <option value="{{ $leaveType->id }}" {{ old('leave_type_id') == $leaveType->id ? 'selected' : '' }}>
                                        {{ $leaveType->name }} ({{ $leaveType->days_per_year }} days/year)
                                    </option>
                                @endforeach
                            </select>
                            @error('leave_type_id')
                                <p id="leave_type_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Date Range -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-field name="start_date" label="Start Date" type="date" :value="old('start_date')" required />
                            </div>

                            <div>
                                <x-field name="end_date" label="End Date" type="date" :value="old('end_date')" required />
                            </div>
                        </div>

                        <!-- Reason -->
                        <div>
                            <label for="reason" class="form-label">Reason</label>
                            <textarea name="reason" id="reason" rows="4"
                                class="form-control @error('reason') border-red-500 @enderror"
                                placeholder="Optional: Provide a reason for your leave request" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>{{ old('reason') }}</textarea>
                            @error('reason')
                                <p id="reason-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3">
                        <a href="{{ route('leaves.index') }}" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Submit request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
