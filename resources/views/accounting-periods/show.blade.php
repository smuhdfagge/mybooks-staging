<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $accountingPeriod->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $accountingPeriod->start_date->format('M d, Y') }} - {{ $accountingPeriod->end_date->format('M d, Y') }}
                </p>
            </div>
            <div class="flex gap-2">
                @if(!$accountingPeriod->isLocked())
                    <a href="{{ route('accounting-periods.edit', $accountingPeriod) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                        Edit
                    </a>
                @endif
                <a href="{{ route('accounting-periods.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">


            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Period Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Status Card -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Period Status</h3>
                                @if($accountingPeriod->status === 'open')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                                        Open
                                    </span>
                                @elseif($accountingPeriod->status === 'closed')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <span class="w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>
                                        Closed
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                                        Locked
                                    </span>
                                @endif
                            </div>

                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $accountingPeriod->start_date->format('F d, Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $accountingPeriod->end_date->format('F d, Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fiscal Year</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $accountingPeriod->fiscal_year ?? 'Not set' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Year End Period</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $accountingPeriod->is_year_end ? 'Yes' : 'No' }}</dd>
                                </div>
                                @if($accountingPeriod->closed_at)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Closed On</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $accountingPeriod->closed_at->format('F d, Y g:i A') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Closed By</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $accountingPeriod->closedBy?->name ?? 'Unknown' }}</dd>
                                </div>
                                @endif
                            </dl>

                            @if($accountingPeriod->closing_notes)
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Closing Notes</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $accountingPeriod->closing_notes }}</dd>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Transaction Summary -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Transaction Summary</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div class="bg-brand-50 dark:bg-brand-900/20 rounded-lg p-4">
                                    <p class="text-sm text-brand-600 dark:text-brand-300 font-medium">Invoices</p>
                                    <p class="text-2xl font-bold text-brand-700 dark:text-brand-300">{{ $summary['invoices']->count }}</p>
                                    <p class="text-sm text-brand-500 dark:text-brand-300">{{ number_format($summary['invoices']->total, 2) }}</p>
                                </div>
                                <div class="bg-brand-50 dark:bg-brand-900/20 rounded-lg p-4">
                                    <p class="text-sm text-brand-600 dark:text-brand-400 font-medium">Bills</p>
                                    <p class="text-2xl font-bold text-brand-700 dark:text-brand-300">{{ $summary['bills']->count }}</p>
                                    <p class="text-sm text-brand-500 dark:text-brand-400">{{ number_format($summary['bills']->total, 2) }}</p>
                                </div>
                                <div class="bg-brand-50 dark:bg-brand-900/20 rounded-lg p-4">
                                    <p class="text-sm text-brand-600 dark:text-brand-400 font-medium">Expenses</p>
                                    <p class="text-2xl font-bold text-brand-700 dark:text-brand-300">{{ $summary['expenses']->count }}</p>
                                    <p class="text-sm text-brand-500 dark:text-brand-400">{{ number_format($summary['expenses']->total, 2) }}</p>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                    <p class="text-sm text-green-600 dark:text-green-400 font-medium">Payments Received</p>
                                    <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $summary['payments_received']->count }}</p>
                                    <p class="text-sm text-green-500 dark:text-green-400">{{ number_format($summary['payments_received']->total, 2) }}</p>
                                </div>
                                <div class="bg-brand-50 dark:bg-brand-900/20 rounded-lg p-4">
                                    <p class="text-sm text-brand-700 dark:text-brand-300 font-medium">Payments Made</p>
                                    <p class="text-2xl font-bold text-brand-800 dark:text-brand-300">{{ $summary['payments_made']->count }}</p>
                                    <p class="text-sm text-brand-700 dark:text-brand-300">{{ number_format($summary['payments_made']->total, 2) }}</p>
                                </div>
                                <div class="bg-brand-50 dark:bg-brand-900/20 rounded-lg p-4">
                                    <p class="text-sm text-brand-600 dark:text-brand-300 font-medium">Journal Entries</p>
                                    <p class="text-2xl font-bold text-brand-700 dark:text-brand-300">{{ $summary['journals']->count }}</p>
                                    <p class="text-sm text-brand-500 dark:text-brand-300">{{ number_format($summary['journals']->total_debit, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Panel -->
                <div class="space-y-6">
                    <!-- Period Actions -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Actions</h3>
                            
                            @if($accountingPeriod->isOpen())
                                <!-- Close Period -->
                                <form action="{{ route('accounting-periods.close', $accountingPeriod) }}" method="POST" class="mb-4">
                                    @csrf
                                    <div class="mb-3">
                                        <label for="closing_notes" class="form-label">Closing Notes (Optional)</label>
                                        <textarea name="closing_notes" id="closing_notes" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" placeholder="Enter any notes for this period closing..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="confirm" value="1" class="rounded border-gray-300 dark:border-gray-600 text-brand-600 shadow-sm focus:ring-brand-500 dark:text-brand-300" required>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">I understand that closing this period will prevent any transaction modifications.</span>
                                        </label>
                                    </div>
                                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        Close Period
                                    </button>
                                </form>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        <strong>Warning:</strong> Closing a period prevents creating, editing, or deleting transactions dated within this period.
                                    </p>
                                </div>
                            @elseif($accountingPeriod->isClosed() && !$accountingPeriod->isLocked())
                                <!-- Reopen Period -->
                                <form action="{{ route('accounting-periods.reopen', $accountingPeriod) }}" method="POST" class="mb-4">
                                    @csrf
                                    <div class="mb-3">
                                        <x-field name="reason" label="Reason for reopening" type="textarea" rows="2" required :value="old('reason')"
                                                 help="Kept in the lock date history on the accounting periods page." />
                                    </div>
                                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition" data-confirm="Are you sure you want to reopen this period?">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                        </svg>
                                        Reopen Period
                                    </button>
                                </form>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                                    <!-- Lock Period Permanently -->
                                    <form action="{{ route('accounting-periods.lock', $accountingPeriod) }}" method="POST">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="lock_notes" class="form-label">Year-End Notes (Optional)</label>
                                            <textarea name="closing_notes" id="lock_notes" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" placeholder="Enter year-end closing notes..."></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="confirm" value="1" class="rounded border-gray-300 dark:border-gray-600 text-red-600 shadow-sm focus:ring-red-500" required>
                                                <span class="ml-2 text-sm text-red-600 dark:text-red-400">I understand this action is <strong>PERMANENT</strong> and cannot be undone.</span>
                                            </label>
                                        </div>
                                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition" data-confirm="Are you absolutely sure? This action CANNOT be undone!">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                            Lock Permanently (Year-End)
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This period is permanently locked and cannot be modified.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Delete Period (only if open) -->
                    @if($accountingPeriod->isOpen())
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-red-600 dark:text-red-400 mb-4">Danger Zone</h3>
                            <form action="{{ route('accounting-periods.destroy', $accountingPeriod) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded-md font-semibold text-xs text-red-700 dark:text-red-300 uppercase tracking-widest hover:bg-red-200 dark:hover:bg-red-900/50 transition" data-confirm="Are you sure you want to delete this period?">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Delete Period
                                </button>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
