@php
    $feedToReview = \App\Models\BankFeedLine::toReview()->count();
    $feedAttention = \App\Models\BankFeedConnection::where('status', 'needs_reauthorisation')->count();
    $feedLinked = \App\Models\BankFeedConnection::counted()->count();
@endphp
<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pt-6" data-testid="feed-summary">
    <div class="card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="text-sm text-gray-700 dark:text-gray-300">
            <span class="font-medium text-gray-900 dark:text-gray-100">Bank feeds:</span>
            @if($feedLinked === 0)
                none connected yet.
            @else
                {{ $feedLinked }} {{ \Illuminate\Support\Str::plural('account', $feedLinked) }} connected,
                <a href="{{ route('bank-feeds.lines') }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $feedToReview }} {{ \Illuminate\Support\Str::plural('line', $feedToReview) }} to review</a>.
                @if($feedAttention > 0)
                    <span class="text-yellow-700 dark:text-yellow-300">{{ $feedAttention === 1 ? '1 account needs' : $feedAttention.' accounts need' }} you to log in to the bank again.</span>
                @endif
            @endif
        </div>
        <a href="{{ route('bank-feeds.index') }}" class="btn-secondary self-start sm:self-auto">Bank feeds</a>
    </div>
</div>
