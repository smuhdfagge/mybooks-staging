<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="'Journal '.$journal->journal_number">
            <x-slot name="badge"><x-status-badge :status="$journal->is_posted ? 'posted' : 'draft'" :label="$journal->is_posted ? 'Posted' : 'Draft'" /></x-slot>
            <x-slot name="description">{{ $journal->journal_date->format('j M Y') }}{{ $journal->description ? ' · '.$journal->description : '' }}</x-slot>
            @if (! $journal->is_posted)
                @can('delete journals')
                    <x-slot name="more">
                        <x-table.menu-item danger :post="route('journals.destroy', $journal)" method="DELETE" confirm="Delete this journal?">Delete</x-table.menu-item>
                    </x-slot>
                @endcan
                <x-slot name="actions">
                    @can('edit journals')
                        <a href="{{ route('journals.edit', $journal) }}" class="btn-secondary">Edit</a>
                    @endcan
                    @can('post journals')
                        <form action="{{ route('journals.post', $journal) }}" method="POST" data-confirm="Post this journal? It can't be changed afterwards.">
                            @csrf
                            <button type="submit" class="btn-new">Post journal</button>
                        </form>
                    @endcan
                </x-slot>
            @endif
        </x-table.page-header>
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
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->journal_date->format('j M Y') }}</dd>
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
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->posted_at->format('j M Y, H:i') }}</dd>
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
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $journal->created_at->format('j M Y, H:i') }}</dd>
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
                    <x-table caption="Journal lines">
<x-slot name="head">
                                    <x-table.th>Account</x-table.th>
                                    <x-table.th>Description</x-table.th>
                                    <x-table.th num>Debit</x-table.th>
                                    <x-table.th num>Credit</x-table.th>
</x-slot>

                                @foreach($journal->entries as $entry)
                                    <tr>
                                        <td>
                                            <a href="{{ route('reports.general-ledger', ['account_id' => $entry->account_id]) }}" class="tbl-link font-medium">{{ $entry->account->account_code }} {{ $entry->account->name }}</a>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                                                {{ $entry->account->type }}
                                            </div>
                                        </td>
                                        <td>
                                            {{ $entry->description ?? '—' }}
                                        </td>
                                        <td class="num {{ $entry->debit > 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'tbl-zero' }}">
                                            {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}
                                        </td>
                                        <td class="num {{ $entry->credit > 0 ? 'font-medium text-gray-900 dark:text-gray-100' : 'tbl-zero' }}">
                                            {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
<x-slot name="foot">
                                <tr>
                                    <th scope="row" colspan="2">Total</th>
                                    <td class="num">@fig($journal->total_debit, false)</td>
                                    <td class="num">@fig($journal->total_credit, false)</td>
                                </tr>
</x-slot>
</x-table>
                </div>
            </div>

            @if ($journal->is_posted && ($journal->isScheduleRelease() || $journal->isAutoReversal()))
                <p class="mt-6 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    @if ($journal->isScheduleRelease())
                        This journal is one month released from a prepaid or deferred revenue schedule. It can't be edited, deleted or voided on its own; cancel the schedule to stop the months still to come.
                    @else
                        This journal was posted automatically to reverse {{ $journal->reversalOf?->journal_number }}. It can't be edited, deleted or voided on its own.
                    @endif
                </p>
            @endif
        </div>
    </div>
</x-app-layout>
