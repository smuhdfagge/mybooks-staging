<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Bank feeds</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('bank-feeds.lines') }}" class="btn-secondary">Review bank lines</a>
                @can('edit banks')
                    @if($live && ! $limitReached)
                        <a href="{{ route('bank-feeds.connect') }}" class="btn-primary" data-testid="connect-feed">Connect a bank account</a>
                    @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @include('bank-feeds._not-set-up')

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Link your bank account and MyBooks reads its transactions (read only; we never see your login details and keep only the last 4 digits of the account number).
                You then match each line to something already in MyBooks, or record it from the line. Nothing is posted until you click.
                <span class="block mt-1" data-testid="plan-limit">{{ $limitSummary }}</span>
            </p>

            @if($limitReached && $live)
                <div class="rounded-lg border border-yellow-300 bg-yellow-50 dark:bg-yellow-900/30 p-3 text-sm text-yellow-800 dark:text-yellow-200">
                    {{ app(\App\Services\BankFeeds\BankFeedLimit::class)->reachedMessage(auth()->user()->tenant) }}
                </div>
            @endif

            <div class="space-y-4">
                @forelse($connections as $c)
                    @php($status = $c->statusEnum())
                    <x-card class="p-5" data-testid="feed-{{ $c->id }}">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $c->title() }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $c->account_name }}
                                    @if($c->bank) · feeds <a class="text-brand-600 dark:text-brand-300 hover:underline" href="{{ route('banks.show', $c->bank_id) }}">{{ $c->bank->name }}</a> @endif
                                </p>
                                <p class="mt-2 text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ match($status->value) { 'linked' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300', 'needs_reauthorisation', 'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300', 'error' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300', default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' } }}">{{ $status->label() }}</span>
                                    <span class="text-gray-500 dark:text-gray-400 ml-2">
                                        @if($c->last_synced_at) Last read {{ $c->last_synced_at->diffForHumans() }} @else Not read yet @endif
                                    </span>
                                </p>
                                @if($c->last_error && $c->isActive())
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $c->last_error }}</p>
                                @endif
                                @if($c->providerBalance() !== null)
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Balance at the bank: <strong>@money($c->providerBalance())</strong></p>
                                @endif
                                @if(($toReview[$c->id] ?? 0) > 0)
                                    <p class="mt-1 text-sm"><a class="text-brand-600 dark:text-brand-300 hover:underline" href="{{ route('bank-feeds.lines', ['connectionFilter' => $c->id]) }}">{{ $toReview[$c->id] }} {{ \Illuminate\Support\Str::plural('line', $toReview[$c->id]) }} to review</a></p>
                                @endif
                            </div>
                            @can('edit banks')
                                <div class="flex flex-wrap gap-2 sm:justify-end">
                                    @if($status === \App\Enums\BankFeedConnectionStatus::NeedsReauthorisation)
                                        <form method="POST" action="{{ route('bank-feeds.reconnect', $c) }}">@csrf
                                            <button class="btn-primary" type="submit">Reconnect</button>
                                        </form>
                                    @endif
                                    @if($c->isActive())
                                        <form method="POST" action="{{ route('bank-feeds.sync', $c) }}">@csrf
                                            <button class="btn-secondary" type="submit">Sync now</button>
                                        </form>
                                        <form method="POST" action="{{ route('bank-feeds.disconnect', $c) }}" data-confirm="Disconnect this bank account? The lines already read are kept, but no new ones will arrive.">@csrf @method('DELETE')
                                            <button class="btn-secondary text-red-700" type="submit">Disconnect</button>
                                        </form>
                                    @endif
                                </div>
                            @endcan
                        </div>
                    </x-card>
                @empty
                    <x-card class="p-8 text-center text-gray-500 dark:text-gray-400">No bank account is connected yet.</x-card>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
