<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $expense->expense_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $expense->expense_date->format('F d, Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($expense->canBeEdited())
                    <a href="{{ route('expenses.edit', $expense) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                @endif
                <a href="{{ route('expenses.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
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


            <!-- Status and Amount Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-red-100 dark:bg-red-900/50 rounded-full p-4">
                                <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($expense->amount, 2) }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Status Badge -->
                            @php
                                $statusColors = [
                                    'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    'pending_approval' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-400',
                                    'approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-400',
                                    'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400',
                                    'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$expense->status] ?? $statusColors['draft'] }}">
                                {{ $expense->status_label }}
                            </span>
                            @if($expense->is_billable)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-400">
                                    Billable
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Approval Workflow Actions -->
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex flex-wrap gap-3">
                            <!-- Submit for Approval -->
                            @if($expense->canBeSubmitted())
                                <form action="{{ route('expenses.submit', $expense) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Submit for Approval
                                    </button>
                                </form>
                            @endif

                            <!-- Admin Actions -->
                            @if(auth()->user()->hasRole('admin') || auth()->user()->isSuperAdmin())
                                @if($expense->canBeApproved())
                                    <form action="{{ route('expenses.approve', $expense) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Approve
                                        </button>
                                    </form>
                                @endif

                                @if($expense->canBeRejected())
                                    <button type="button" data-open-modal="reject-expense" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Reject
                                    </button>
                                @endif

                                @if($expense->canBeMarkedAsPaid())
                                    <form action="{{ route('expenses.mark-paid', $expense) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Mark as Paid
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rejection Reason Alert -->
            @if($expense->isRejected() && $expense->rejection_reason)
                <div class="mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-400 dark:border-red-600 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-400">Expense Rejected</h3>
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $expense->rejection_reason }}</p>
                            @if($expense->rejectedByUser)
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                    Rejected by {{ $expense->rejectedByUser->name }} on {{ $expense->rejected_at->format('M d, Y g:i A') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Expense Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Expense Details</h3>
                        <dl class="space-y-4">
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expense Number</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->expense_number }}</dd>
                            </div>

                            @if($expense->name)
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expense Name</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $expense->name }}</dd>
                            </div>
                            @endif

                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->expense_date->format('M d, Y') }}</dd>
                            </div>

                            @if($expense->expenseAccount)
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expense Account</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->expenseAccount->name }}</dd>
                            </div>
                            @endif

                            @if($expense->paidThroughAccount)
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Paid Through</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->paidThroughAccount->name }}</dd>
                            </div>
                            @endif

                            @if($expense->reference)
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reference</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->reference }}</dd>
                            </div>
                            @endif

                            @if($expense->createdBy)
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created By</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->createdBy->name }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Vendor & Approval Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Vendor Information</h3>
                        @if($expense->vendor)
                            <dl class="space-y-4">
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('vendors.show', $expense->vendor) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">
                                            {{ $expense->vendor->name }}
                                        </a>
                                    </dd>
                                </div>
                                @if($expense->vendor->company_name)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Company</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->vendor->company_name }}</dd>
                                </div>
                                @endif
                                @if($expense->vendor->email)
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">
                                        <a href="mailto:{{ $expense->vendor->email }}" class="text-indigo-600 dark:text-indigo-400">{{ $expense->vendor->email }}</a>
                                    </dd>
                                </div>
                                @endif
                            </dl>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">No vendor associated with this expense.</p>
                        @endif

                        <!-- Approval Information -->
                        @if($expense->isApproved() || $expense->isPaid())
                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Approval Information</h4>
                                <dl class="space-y-3">
                                    @if($expense->approvedByUser)
                                    <div class="flex justify-between">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved By</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->approvedByUser->name }}</dd>
                                    </div>
                                    @endif
                                    @if($expense->approved_at)
                                    <div class="flex justify-between">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved At</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $expense->approved_at->format('M d, Y g:i A') }}</dd>
                                    </div>
                                    @endif
                                </dl>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Description -->
            @if($expense->description)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Description</h3>
                    <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $expense->description }}</p>
                </div>
            </div>
            @endif

            <!-- Journal Entry / Double-Entry (only shown for paid expenses) -->
            @if($expense->journal)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center justify-between">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Journal Entry
                        </span>
                        <a href="{{ route('journals.show', $expense->journal) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $expense->journal->journal_number }}
                        </a>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($expense->journal->entries as $entry)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400 mr-2">{{ $entry->account->account_code }}</span>
                                        {{ $entry->account->name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $entry->debit > 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $entry->credit > 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700">
                                <tr class="font-semibold">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($expense->journal->total_debit, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($expense->journal->total_credit, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Posted on {{ $expense->journal->posted_at?->format('M d, Y g:i A') ?? 'Not posted' }}
                    </p>
                </div>
            </div>
            @elseif(!$expense->isPaid())
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm text-yellow-800 dark:text-yellow-300">
                            Journal entries will be created once this expense is approved and marked as paid.
                        </span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Actions -->
            @if($expense->canBeEdited())
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6 flex flex-wrap gap-4">
                    <a href="{{ route('expenses.edit', $expense) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Expense
                    </a>

                    <form action="{{ route('expenses.destroy', $expense) }}" method="POST" class="inline" data-confirm="Are you sure you want to delete this expense?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Rejection Modal -->
    <x-modal name="reject-expense" maxWidth="lg" aria-labelledby="modal-title">
                <form action="{{ route('expenses.reject', $expense) }}" method="POST">
                    @csrf
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900/50 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                                    Reject Expense
                                </h3>
                                <div class="mt-4">
                                    <label for="rejection_reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Reason for Rejection (Optional)
                                    </label>
                                    <textarea
                                        id="rejection_reason"
                                        name="rejection_reason"
                                        rows="3"
                                        class="form-control"
                                        placeholder="Enter reason for rejection..."
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto sm:text-sm">
                            Reject Expense
                        </button>
                        <button type="button" data-close-modal="reject-expense" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
    </x-modal>
</x-app-layout>
