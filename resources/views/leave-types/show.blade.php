<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $leaveType->name }}</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('leave-types.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">Back to List</a>
                @can('edit leave-types')
                    <a href="{{ route('leave-types.edit', $leaveType) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 transition ease-in-out duration-150">Edit</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Code</dt><dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $leaveType->code ?: '—' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Days per year</dt><dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $leaveType->days_per_year }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Paid</dt><dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $leaveType->is_paid ? 'Yes' : 'No' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Carry forward</dt><dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $leaveType->is_carry_forward ? 'Up to '.$leaveType->max_carry_forward_days.' days' : 'No' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt><dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $leaveType->is_active ? 'Active' : 'Inactive' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Leave requests</dt><dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $leaveType->leaves_count }}</dd></div>
                </dl>
                @if($leaveType->description)
                    <p class="mt-6 text-sm text-gray-700 dark:text-gray-300">{{ $leaveType->description }}</p>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Recent requests</h3>
                @forelse($recentLeaves as $leave)
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700 text-sm">
                        <span class="text-gray-900 dark:text-gray-100">{{ $leave->employee?->full_name ?? 'Unknown employee' }}</span>
                        <span class="text-gray-600 dark:text-gray-400">{{ optional($leave->start_date)->format('M d, Y') }} – {{ optional($leave->end_date)->format('M d, Y') }} · {{ ucfirst((string) $leave->status) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No requests for this leave type yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
