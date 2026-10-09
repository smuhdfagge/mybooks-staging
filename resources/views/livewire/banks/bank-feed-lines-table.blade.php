<div class="relative">
    <x-table-loading />

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        @foreach(['new' => 'To review', 'matched' => 'Matched', 'created' => 'Recorded', 'ignored' => 'Ignored'] as $key => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $key }}')" data-testid="count-{{ $key }}"
                    class="card p-3 text-left {{ $statusFilter === $key ? 'ring-2 ring-brand-500' : '' }}">
                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <span class="block text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    <x-flash-messages :success-message="$successMessage" :error-message="$errorMessage" />

    <div class="card">
        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-3 mb-4">
                <input aria-label="Search narration or amount" wire:model.live.debounce.300ms="search" type="text" placeholder="Search words or amount..." class="form-control lg:col-span-2">
                <select aria-label="Account" wire:model.live="connectionFilter" class="form-control">
                    <option value="">All accounts</option>
                    @foreach($connections as $c)
                        <option value="{{ $c->id }}">{{ $c->title() }}</option>
                    @endforeach
                </select>
                <select aria-label="Status" wire:model.live="statusFilter" class="form-control">
                    <option value="">Any status</option>
                    <option value="new">To review</option>
                    <option value="matched">Matched</option>
                    <option value="created">Recorded</option>
                    <option value="ignored">Ignored</option>
                </select>
                <select aria-label="Money in or out" wire:model.live="directionFilter" class="form-control">
                    <option value="">In and out</option>
                    <option value="credit">Money in</option>
                    <option value="debit">Money out</option>
                </select>
                <div class="flex gap-2 sm:col-span-2 2xl:col-span-1">
                    <input aria-label="From date" wire:model.live="dateFrom" type="date" class="form-control min-w-0 flex-1">
                    <input aria-label="To date" wire:model.live="dateTo" type="date" class="form-control min-w-0 flex-1">
                </div>
            </div>

            @if($canReconcile && $statusFilter === 'new')
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-primary" data-testid="accept-all"
                            wire:click="acceptAllSuggested"
                            wire:confirm="Accept every suggestion marked High? Each line will be matched to the record suggested. Nothing new is posted, and a match can be undone.">
                        Accept all High suggestions
                    </button>
                    @if(count($selectedItems) > 0)
                        <button type="button" class="btn-secondary" wire:click="ignoreSelected"
                                wire:confirm="Ignore the {{ count($selectedItems) }} ticked lines? You can bring them back from the Ignored list.">
                            Ignore {{ count($selectedItems) }} ticked
                        </button>
                    @endif
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 line-items">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            @if($canReconcile && $statusFilter === 'new')<th class="px-3 py-3 w-8"></th>@endif
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">What the bank says</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">In MyBooks</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($lines as $line)
                            @php($s = $suggestions[$line->id] ?? null)
                            <tr wire:key="line-{{ $line->id }}" data-testid="line-{{ $line->id }}">
                                @if($canReconcile && $statusFilter === 'new')
                                    <td class="px-3 py-3" data-cell="main"><input aria-label="Select line" type="checkbox" wire:model.live="selectedItems" value="{{ $line->id }}" class="rounded border-gray-300 text-brand-600 dark:text-brand-300"></td>
                                @endif
                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" data-label="Date">{{ $line->date->format('j M Y') }}</td>
                                <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100" data-cell="main">
                                    <div class="break-words">{{ $line->narration ?: '—' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $line->connection?->title() }}</div>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-sm text-right font-medium {{ $line->isCredit() ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" data-label="Amount">
                                    {{ $line->isCredit() ? '+' : '−' }}@money($line->amount)
                                </td>
                                <td class="px-3 py-3 text-sm" data-cell="main">
                                    @if($line->status === 'new')
                                        @if($s)
                                            <div class="text-gray-900 dark:text-gray-100">
                                                <a class="text-brand-600 dark:text-brand-300 hover:underline" href="{{ $s->candidate->url ?? '#' }}" target="_blank" rel="noopener">{{ $s->candidate->describe() }}</a>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $s->candidate->date->format('j M') }}</span>
                                            </div>
                                            <span class="mt-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ ['High' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300', 'Medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300', 'Low' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'][$s->label()] }}" data-testid="confidence-{{ $line->id }}">{{ $s->label() }} confidence</span>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">Nothing matches yet</span>
                                        @endif
                                    @elseif($line->status === 'ignored')
                                        <x-status-badge status="cancelled" label="Ignored" />
                                    @else
                                        <x-status-badge :status="$line->status === 'matched' ? 'accepted' : 'completed'" :label="$line->status === 'matched' ? 'Matched' : 'Recorded'" />
                                        @if($line->matched)
                                            <span class="text-gray-700 dark:text-gray-300">{{ $line->matched->payment_number ?? $line->matched->expense_number ?? $line->matched->journal_number ?? '' }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-right" data-cell="actions">
                                    @if($canReconcile)
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @if($line->status === 'new')
                                                @if($s)
                                                    <button type="button" class="btn-primary" wire:click="accept({{ $line->id }}, @js($s->candidate->record::class), {{ $s->candidate->record->getKey() }})" data-testid="accept-{{ $line->id }}">Accept</button>
                                                    <button type="button" class="btn-secondary" wire:click="reject({{ $line->id }}, @js($s->candidate->record::class), {{ $s->candidate->record->getKey() }})" data-testid="reject-{{ $line->id }}">Not this one</button>
                                                @endif
                                                <a href="{{ route('bank-feeds.lines.show', $line) }}" class="btn-secondary" data-testid="record-{{ $line->id }}">Record it</a>
                                                <button type="button" class="btn-secondary" wire:click="ignore({{ $line->id }})" data-testid="ignore-{{ $line->id }}">Ignore</button>
                                            @elseif($line->status === 'ignored')
                                                <button type="button" class="btn-secondary" wire:click="unignore({{ $line->id }})">Bring back</button>
                                            @elseif($line->status === 'matched')
                                                <button type="button" class="btn-secondary" wire:click="undo({{ $line->id }})" wire:confirm="Take this match back?">Undo</button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                {{ $statusFilter === 'new' ? 'Nothing to review. New lines appear here when the bank sends them.' : 'No lines found.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $lines->links() }}</div>
        </div>
    </div>
</div>
