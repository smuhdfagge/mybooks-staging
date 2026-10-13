<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Edit leave request" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('leaves.update', $leave) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">
                        <!-- Employee (Read-only) -->
                        <div>
                            <label class="form-label">Employee</label>
                            <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                                <div class="h-8 w-8 flex-shrink-0 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                        {{ strtoupper(substr($leave->employee->first_name ?? '', 0, 1)) }}{{ strtoupper(substr($leave->employee->last_name ?? '', 0, 1)) }}
                                    </span>
                                </div>
                                <span class="ml-3 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $leave->employee->first_name ?? '' }} {{ $leave->employee->last_name ?? '' }}
                                </span>
                            </div>
                        </div>

                        <!-- Leave Type (Read-only) -->
                        <div>
                            <label class="form-label">Leave Type</label>
                            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                                <span class="text-sm text-gray-900 dark:text-gray-100">{{ $leave->leaveType->name ?? 'N/A' }}</span>
                            </div>
                        </div>

                        <!-- Date Range -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-field name="start_date" label="Start Date" type="date" :value="old('start_date', $leave->start_date->format('Y-m-d'))" required />
                            </div>

                            <div>
                                <x-field name="end_date" label="End Date" type="date" :value="old('end_date', $leave->end_date->format('Y-m-d'))" required />
                            </div>
                        </div>

                        <!-- Reason -->
                        <div>
                            <label for="reason" class="form-label">Reason</label>
                            <textarea name="reason" id="reason" rows="4"
                                class="form-control @error('reason') border-red-500 @enderror"
                                placeholder="Optional: Provide a reason for your leave request" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror>{{ old('reason', $leave->reason) }}</textarea>
                            @error('reason')
                                <p id="reason-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3">
                        <a href="{{ route('leaves.show', $leave) }}" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
