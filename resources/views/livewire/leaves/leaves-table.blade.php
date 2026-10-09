<div class="relative">
    <x-table-loading />
    <!-- Flash Messages -->
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <!-- Filters -->
    <div class="mb-4 sm:mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 sm:gap-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="form-label">Search</label>
                <input aria-label="Search by employee" type="text" wire:model.live.debounce.300ms="search" placeholder="Search by employee..."
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
            </div>
            <div>
                <label for="employee" class="form-label">Employee</label>
                <select id="employee" wire:model.live="employee" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="">All Employees</option>
                    @foreach($employees ?? [] as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="leaveType" class="form-label">Leave Type</label>
                <select id="leaveType" wire:model.live="leaveType" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="">All Types</option>
                    @foreach($leaveTypes ?? [] as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" wire:model.live="status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div>
                <label for="perPage" class="form-label">Per Page</label>
                <select id="perPage" wire:model.live="perPage" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
            <!-- Bulk Actions -->
            <x-bulk-actions :actions="['approve' => 'Approve', 'reject' => 'Reject', 'delete' => 'Delete']" :selectedCount="count($selectedItems)" />
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">
                            <input aria-label="Select all" type="checkbox" wire:model.live="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                        </th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee</th>
                        <th scope="col" class="hidden sm:table-cell px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Leave Type</th>
                        <x-sort-header field="start_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Dates</x-sort-header>
                        <x-sort-header field="days" :sort-field="$sortField" :sort-direction="$sortDirection" class="hidden md:table-cell px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-600">Days</x-sort-header>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($leaves as $leave)
                        <tr wire:key="leave-{{ $leave->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-4">
                                <input aria-label="Select row" type="checkbox" wire:model.live="selectedItems" value="{{ $leave->id }}"
                                    class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-gray-700 dark:text-brand-300">
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 sm:h-10 sm:w-10 flex-shrink-0 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ strtoupper(substr($leave->employee->first_name ?? '', 0, 1)) }}{{ strtoupper(substr($leave->employee->last_name ?? '', 0, 1)) }}</span>
                                    </div>
                                    <div class="ml-3 min-w-0">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $leave->employee->first_name ?? '' }} {{ $leave->employee->last_name ?? '' }}</div>
                                        <div class="sm:hidden text-xs text-gray-500 dark:text-gray-400">{{ $leave->leaveType->name ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden sm:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $leave->leaveType->name ?? '-' }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ $leave->start_date->format('M d, Y') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">to {{ $leave->end_date->format('M d, Y') }}</div>
                            </td>
                            <td class="hidden md:table-cell px-4 sm:px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">{{ $leave->days }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $leave->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' : '' }}
                                    {{ $leave->status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : '' }}
                                    {{ $leave->status === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' : '' }}">
                                    {{ ucfirst($leave->status) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-1 sm:space-x-2">
                                    <a href="{{ route('leaves.show', $leave) }}" class="p-1 text-brand-600 hover:text-brand-900 dark:text-brand-300" title="View" aria-label="View">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    @if($leave->status === 'pending')
                                        <a href="{{ route('leaves.edit', $leave) }}" class="p-1 text-yellow-600 hover:text-yellow-900 dark:text-yellow-400" title="Edit" aria-label="Edit">
                                            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 sm:px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No leave requests found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new leave request.</p>
                                <div class="mt-6">
                                    <a href="{{ route('leaves.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand-600 hover:bg-brand-700">
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        Request Leave
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($leaves->hasPages())
            <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">{{ $leaves->links() }}</div>
        @endif
    </div>
</div>
