<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Leave Request Details
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $leave->employee->first_name ?? '' }} {{ $leave->employee->last_name ?? '' }} · {{ $leave->leaveType->name ?? 'N/A' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($leave->status === 'pending')
                    <a href="{{ route('leaves.edit', $leave) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                @endif
                <a href="{{ route('leaves.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Status Banner -->
            <div class="mb-6">
                @if($leave->status === 'pending')
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-yellow-800 dark:text-yellow-200 font-medium">This leave request is pending approval</span>
                        </div>
                    </div>
                @elseif($leave->status === 'approved')
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-green-800 dark:text-green-200 font-medium">This leave request has been approved</span>
                        </div>
                    </div>
                @elseif($leave->status === 'rejected')
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-red-800 dark:text-red-200 font-medium">This leave request has been rejected</span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Leave Details -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                Leave Details
                            </h3>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Leave Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->leaveType->name ?? 'N/A' }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Duration</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->days }} day(s)</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->start_date->format('F d, Y') }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->end_date->format('F d, Y') }}</dd>
                                </div>

                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reason</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->reason ?? 'No reason provided' }}</dd>
                                </div>

                                @if($leave->rejection_reason)
                                    <div class="sm:col-span-2">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Rejection Reason</dt>
                                        <dd class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $leave->rejection_reason }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Approval Information -->
                    @if($leave->status !== 'pending' && $leave->approvedBy)
                        <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                    Approval Information
                                </h3>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $leave->status === 'approved' ? 'Approved By' : 'Rejected By' }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->approvedBy->name ?? 'N/A' }}</dd>
                                    </div>

                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $leave->status === 'approved' ? 'Approved At' : 'Rejected At' }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->approved_at?->format('F d, Y g:i A') ?? 'N/A' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Employee Information -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                Employee
                            </h3>
                            <div class="flex items-center mb-4">
                                <div class="h-12 w-12 flex-shrink-0 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                    <span class="text-lg font-medium text-gray-600 dark:text-gray-300">
                                        {{ strtoupper(substr($leave->employee->first_name ?? '', 0, 1)) }}{{ strtoupper(substr($leave->employee->last_name ?? '', 0, 1)) }}
                                    </span>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $leave->employee->first_name ?? '' }} {{ $leave->employee->last_name ?? '' }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $leave->employee->designation->title ?? 'No Designation' }}
                                    </div>
                                </div>
                            </div>
                            <dl class="space-y-3">
                                @if($leave->employee->email)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->employee->email }}</dd>
                                    </div>
                                @endif
                                @if($leave->employee->department)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Department</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $leave->employee->department->name }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Actions -->
                    @if($leave->status === 'pending')
                        <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                    Actions
                                </h3>
                                <div class="space-y-3">
                                    @can('approve leaves')
                                        <form action="{{ route('leaves.approve', $leave) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Approve
                                            </button>
                                        </form>
                                        <form action="{{ route('leaves.reject', $leave) }}" method="POST" x-data="{ showReason: false }">
                                            @csrf
                                            <div x-show="!showReason">
                                                <button type="button" @click="showReason = true" class="w-full inline-flex items-center justify-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                    Reject
                                                </button>
                                            </div>
                                            <div x-show="showReason" x-cloak class="space-y-2">
                                                <textarea name="rejection_reason" rows="2" required
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                                                    placeholder="Enter rejection reason..."></textarea>
                                                <div class="flex gap-2">
                                                    <button type="button" @click="showReason = false" class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 transition">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                                        Confirm
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    @endcan
                                    @can('delete leaves')
                                        <form action="{{ route('leaves.destroy', $leave) }}" method="POST" data-confirm="Are you sure you want to delete this leave request?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Timestamps -->
            <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                <p>Created: {{ $leave->created_at->format('F d, Y g:i A') }}</p>
                @if($leave->updated_at != $leave->created_at)
                    <p>Last Updated: {{ $leave->updated_at->format('F d, Y g:i A') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
