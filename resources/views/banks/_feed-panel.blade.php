{{-- The linked bank feed as the statement (session 17) --}}
<div class="card mb-6 p-6" data-testid="feed-panel">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Bank feed: {{ $feed['connection']->title() }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if($feed['feed_balance_at']) The bank's balance was read {{ $feed['feed_balance_at']->diffForHumans() }}. @else The bank's balance has not been read yet. @endif
                Lines you match count as cleared in the figures above.
            </p>
        </div>
        <a href="{{ route('bank-feeds.lines', ['connectionFilter' => $feed['connection']->id]) }}" class="btn-secondary self-start">Review bank lines</a>
    </div>

    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">Balance at the bank</dt>
            <dd class="text-xl font-bold text-gray-900 dark:text-gray-100" data-testid="feed-balance">{{ $feed['feed_balance'] === null ? '—' : $bank->currency.' '.number_format($feed['feed_balance'], 2) }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">Balance in MyBooks</dt>
            <dd class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $bank->currency }} {{ number_format($feed['book_balance'], 2) }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">Difference</dt>
            <dd class="text-xl font-bold {{ $feed['difference'] === null ? 'text-gray-500' : ($feed['difference'] == 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') }}" data-testid="feed-difference">{{ $feed['difference'] === null ? '—' : $bank->currency.' '.number_format($feed['difference'], 2) }}</dd>
        </div>
    </dl>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>
            <h4 class="font-medium text-gray-900 dark:text-gray-100">Bank lines not in MyBooks yet ({{ $feed['unmatched_count'] }})</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400">Money in {{ $bank->currency }} {{ number_format($feed['unmatched_in'], 2) }} · money out {{ $bank->currency }} {{ number_format($feed['unmatched_out'], 2) }}</p>
            <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700 text-sm" data-testid="feed-unmatched-lines">
                @forelse($feed['unmatched_lines'] as $l)
                    <li class="py-2 flex justify-between gap-3">
                        <span class="min-w-0 break-words text-gray-800 dark:text-gray-200">{{ $l->date->format('j M') }} · {{ \Illuminate\Support\Str::limit($l->narration ?: 'No description', 60) }}</span>
                        <span class="whitespace-nowrap {{ $l->isCredit() ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $l->isCredit() ? '+' : '−' }}{{ number_format($l->amount, 2) }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500 dark:text-gray-400">Every bank line has been dealt with.</li>
                @endforelse
            </ul>
        </div>
        <div>
            <h4 class="font-medium text-gray-900 dark:text-gray-100">In MyBooks but not at the bank yet ({{ $feed['books_without_line']->count() }})</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400">Cheques not cashed, payments still on their way, or entries to check.</p>
            <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700 text-sm" data-testid="feed-unmatched-books">
                @forelse($feed['books_without_line'] as $c)
                    <li class="py-2 flex justify-between gap-3">
                        <span class="min-w-0 break-words text-gray-800 dark:text-gray-200">{{ $c->date->format('j M') }} · @if($c->url)<a class="text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ $c->url }}">{{ $c->describe() }}</a>@else{{ $c->describe() }}@endif</span>
                        <span class="whitespace-nowrap text-gray-700 dark:text-gray-300">{{ number_format($c->amount, 2) }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500 dark:text-gray-400">Everything in MyBooks has been seen at the bank.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
