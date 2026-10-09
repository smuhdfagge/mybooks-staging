<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $journal->journal_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $journal->journal_date->format('F d, Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(!$journal->is_posted)
                    <a href="{{ route('journals.edit', $journal) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                @endif
                <a href="{{ route('journals.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <!-- Status & Totals Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-brand-100 dark:bg-brand-900/50 rounded-full p-4">
                                <svg class="w-8 h-8 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($journal->total_debit, 2) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            @if($journal->is_posted)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Posted
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-400">
                                    Draft
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Journal Details -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Journal Details</h3>
                        <dl class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Journal Number</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->journal_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->journal_date->format('M d, Y') }}</dd>
                            </div>
                            @if($journal->reference)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reference</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->reference }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 capitalize">{{ $journal->status }}</dd>
                            </div>
                            {{-- Prepaid / deferred schedules (S9) --}}
                            @if($journal->isScheduleRelease())
                            @php($releasedFrom = \App\Models\AccrualSchedule::find($journal->reference_id))
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Released from schedule</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    @if($releasedFrom && \App\Http\Middleware\EnsureFeatureEnabled::enabled('prepaid_schedules'))
                                        <a href="{{ route('accrual-schedules.show', $releasedFrom) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $releasedFrom->schedule_number }}</a>
                                        <span class="text-gray-500 dark:text-gray-400">{{ $releasedFrom->description }}</span>
                                    @else
                                        {{ $releasedFrom?->schedule_number ?? '—' }}
                                    @endif
                                </dd>
                            </div>
                            @endif
                            {{-- Accruals (S8) --}}
                            @if($journal->isAutoReversal())
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Automatic reversal of</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    @if($journal->reversalOf)
                                        <a href="{{ route('journals.show', $journal->reversalOf) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $journal->reversalOf->journal_number }}</a>
                                        <span class="text-gray-500 dark:text-gray-400">dated {{ $journal->reversalOf->journal_date->format('j M Y') }}</span>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            @elseif($journal->reverse_on)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Automatic reversal</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    @if($journal->autoReversal)
                                        Reversed by <a href="{{ route('journals.show', $journal->autoReversal) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $journal->autoReversal->journal_number }}</a>
                                        on {{ $journal->autoReversal->journal_date->format('j M Y') }}
                                    @elseif($journal->status === 'reversed')
                                        Cancelled: this journal was reversed by hand before {{ $journal->reverse_on->format('j M Y') }}.
                                    @else
                                        Reverses on {{ $journal->reverse_on->format('j M Y') }}
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                                            @if(! $journal->is_posted)
                                                Once the journal is posted.
                                            @elseif(! \App\Http\Middleware\EnsureFeatureEnabled::enabled('auto_reversing_journals'))
                                                Automatic reversals are switched off, so this won't be posted for now.
                                            @elseif($journal->reverse_on->lte(today()))
                                                Due now: it is posted at the next daily run.
                                            @else
                                                Posted for you on that date.
                                            @endif
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            @endif
                            @if($journal->is_posted && $journal->posted_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Posted At</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->posted_at->format('M d, Y H:i') }}</dd>
                            </div>
                            @endif
                        </dl>
                        @if($journal->description)
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $journal->description }}</dd>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Audit Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Audit Information</h3>
                        <dl class="space-y-4">
                            @if($journal->createdBy)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created By</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->createdBy->name }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->created_at->format('M d, Y H:i') }}</dd>
                            </div>
                            @if($journal->approvedBy)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved By</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->approvedBy->name }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Journal Entries Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Journal Entries</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($journal->entries as $entry)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $entry->account->account_code }} - {{ $entry->account->name }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                                                {{ $entry->account->type }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $entry->description ?? '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $entry->debit > 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-400' }}">
                                            {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $entry->credit > 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'text-gray-400' }}">
                                            {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <td colspan="2" class="px-6 py-3 text-right text-sm font-bold text-gray-900 dark:text-gray-100">
                                        Totals:
                                    </td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-gray-900 dark:text-gray-100">
                                        {{ number_format($journal->total_debit, 2) }}
                                    </td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-gray-900 dark:text-gray-100">
                                        {{ number_format($journal->total_credit, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6 flex flex-wrap gap-4">
                    @if(!$journal->is_posted)
                        <a href="{{ route('journals.edit', $journal) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Journal
                        </a>

                        <form action="{{ route('journals.post', $journal) }}" method="POST" class="inline" data-confirm="Are you sure you want to post this journal? This action cannot be undone.">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Post Journal
                            </button>
                        </form>

                        <form action="{{ route('journals.destroy', $journal) }}" method="POST" class="inline" data-confirm="Are you sure you want to delete this journal?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete
                            </button>
                        </form>
                    @else
                        <span class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Journal is Posted (Locked)
                        </span>
                        @if($journal->isScheduleRelease())
                            <p class="w-full text-sm text-gray-500 dark:text-gray-400">This journal is one month released from a prepaid or deferred revenue schedule. It can't be edited, deleted or voided on its own; cancel the schedule to stop the months still to come.</p>
                        @endif
                        @if($journal->isAutoReversal())
                            <p class="w-full text-sm text-gray-500 dark:text-gray-400">This journal was posted automatically to reverse {{ $journal->reversalOf?->journal_number }}. It can't be edited, deleted or voided on its own.</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
