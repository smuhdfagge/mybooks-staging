{{--
    Receivables & payables check (session 10): ledger control accounts
    against the customer and supplier statement balances.
--}}
@php
    $secondary = 'inline-flex items-center justify-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Receivables &amp; payables check</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Does the ledger agree with what customers owe you and what you owe suppliers?</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" data-print class="{{ $secondary }}">Print</button>
                <a href="{{ route('reports.index') }}" class="{{ $secondary }}">Back to reports</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-card class="p-4 sm:p-6">
                <form method="GET" action="{{ route('reports.control-reconciliation') }}" class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <div class="sm:w-56"><x-field name="as_of" label="As at" type="date" :value="$asOf" /></div>
                    <button type="submit" class="btn-primary justify-center">Check</button>
                </form>
            </x-card>

            @foreach($sections as $key => $sec)
                @php
                    $isAr = $key === 'receivables';
                    $ok = abs($sec['difference']) < 0.005;
                    $problems = collect($sec['items'])->where('expected', false);
                    $expected = collect($sec['items'])->where('expected', true);
                @endphp
                <x-card>
                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $sec['title'] }}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $sec['account'] ? $sec['account']->account_code.' - '.$sec['account']->name : 'account '.$sec['code'].' not found' }})</span>
                            </h3>
                            @if($ok)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">Agrees</span>
                            @elseif($problems->isEmpty() && abs($sec['unexplained']) < 0.005)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300">Difference explained</span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">Needs a look</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-md bg-gray-50 dark:bg-gray-900/40 p-3">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Balance in the ledger</p>
                                <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">@money($sec['ledger'])</p>
                            </div>
                            <div class="rounded-md bg-gray-50 dark:bg-gray-900/40 p-3">
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isAr ? 'Total of all customer balances' : 'Total of all supplier balances' }}</p>
                                <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">@money($sec['statements'])</p>
                                <a href="{{ route($isAr ? 'reports.accounts-receivable' : 'reports.accounts-payable', ['as_of' => $asOf]) }}" class="text-xs text-brand-600 dark:text-brand-300 hover:underline">{{ $isAr ? 'Aged receivables' : 'Aged payables' }}</a>
                            </div>
                            <div class="rounded-md p-3 {{ $ok ? 'bg-green-50 dark:bg-green-900/20' : 'bg-amber-50 dark:bg-amber-900/20' }}">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Difference</p>
                                <p class="text-xl font-semibold {{ $ok ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300' }}">@money($sec['difference'])</p>
                            </div>
                        </div>

                        @if(! $ok)
                            @foreach([['Needs a look', $problems], ['Expected (no action needed)', $expected]] as [$heading, $list])
                                @if($list->isNotEmpty())
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $heading }}</h4>
                                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-md">
                                            @foreach($list as $item)
                                                <li class="p-3 flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-4 text-sm">
                                                    <div>
                                                        @if($item['url'])
                                                            <a href="{{ $item['url'] }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $item['label'] }}</a>
                                                        @else
                                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $item['label'] }}</span>
                                                        @endif
                                                        @if($item['date'])<span class="text-xs text-gray-500 dark:text-gray-400"> · {{ \Carbon\Carbon::parse($item['date'])->format('j M Y') }}</span>@endif
                                                        <p class="text-gray-600 dark:text-gray-400">{{ $item['detail'] }}</p>
                                                    </div>
                                                    <span class="whitespace-nowrap font-semibold text-gray-900 dark:text-gray-100 sm:text-right">@money($item['amount'])</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                            @if(abs($sec['unexplained']) >= 0.005)
                                <p class="text-sm text-red-700 dark:text-red-300">@money($sec['unexplained']) of the difference isn't explained by the items above.</p>
                            @endif
                            <p class="text-xs text-gray-500 dark:text-gray-400">Amounts are what each item adds to the difference (ledger minus {{ $isAr ? 'customer' : 'supplier' }} balances).</p>
                        @endif
                    </div>
                </x-card>
            @endforeach

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Each invoice, payment, credit note and refund (bill, payment, supplier credit, advance and asset bought on account for suppliers) is matched with its own journal.
                Journals posted straight to Accounts Receivable or Accounts Payable don't show on any customer or supplier statement, so they are listed here.
            </p>
        </div>
    </div>
</x-app-layout>
