{{-- Lower dashboard cards (dashboard upgrade). Figures: DashboardService. --}}
@php
    $user = auth()->user();
    // Customers owe you, by how late (config/brand.php chart.ageing): blue for
    // current, then one ochre ramp, deeper = later. Written out in full so
    // Tailwind keeps the classes.
    $ageing = [
        'bg-[#2F6AAE] dark:bg-[#5B8FD3]',
        'bg-[#D79E36] dark:bg-[#E0AC50]',
        'bg-[#B5781A] dark:bg-[#C9902E]',
        'bg-[#8A5A12] dark:bg-[#A8701A]',
        'bg-[#5C3D0D] dark:bg-[#8A5A12]',
    ];
    $date = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('j M');
    $topRow = array_filter([$cashFlow, $receivables, $payables], fn ($c) => $c !== null);
    $bottomRow = array_filter([$customers, $spend, $recent], fn ($c) => $c !== null);
@endphp
<div class="space-y-4">
    @if ($topRow)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @if ($cashFlow)
                <x-dashboard.card title="Cash flow" :subtitle="'Last 30 days ('.$date($cashFlow['from']).' – '.$date($cashFlow['to']).'), all bank and cash accounts'" id="dash-cashflow"
                    :link="$user->can('view reports') ? route('reports.cash-flow', ['start_date' => $cashFlow['from'], 'end_date' => $cashFlow['to']]) : null" link-text="Cash flow report">
                    <p class="text-2xl font-semibold tabular-nums {{ $cashFlow['net'] >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                        {{ $cashFlow['net'] >= 0 ? '+' : '−' }}@moneyWhole(abs($cashFlow['net']))
                    </p>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ $cashFlow['net'] >= 0 ? 'More came in than went out' : 'More went out than came in' }}</p>
                    <x-dashboard.bars :rows="$cashFlow['lines']" tone="flow" :signed="true" />

                    <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Next 30 days</h3>
                        @php $diff = $cashFlow['dueIn'] - $cashFlow['dueOut']; @endphp
                        <dl class="mt-2 divide-y divide-gray-100 rounded-md bg-gray-50 px-3 text-sm dark:divide-gray-700 dark:bg-gray-900/40">
                            <div class="flex justify-between gap-3 py-1.5">
                                <dt class="text-gray-600 dark:text-gray-400">Due in from customers</dt>
                                <dd class="font-semibold tabular-nums text-gray-900 dark:text-white">@moneyWhole($cashFlow['dueIn'])</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-1.5">
                                <dt class="text-gray-600 dark:text-gray-400">Due out to suppliers</dt>
                                <dd class="font-semibold tabular-nums text-gray-900 dark:text-white">@moneyWhole($cashFlow['dueOut'])</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-1.5">
                                <dt class="font-medium text-gray-800 dark:text-gray-200">Difference</dt>
                                <dd class="font-semibold tabular-nums {{ $diff >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">{{ $diff >= 0 ? '+' : '−' }}@moneyWhole(abs($diff))</dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Invoices and bills due by {{ $date($cashFlow['horizon']) }}, including overdue. Not a forecast.</p>
                    </div>
                </x-dashboard.card>
            @endif

            @if ($receivables)
                <x-dashboard.card title="Customers owe you" subtitle="By how late they are" id="dash-receivables"
                    :link="$user->can('view reports') ? route('reports.accounts-receivable') : null" link-text="Ageing report">
                    <p class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">@moneyWhole($receivables['total'])</p>
                    @if ($receivables['overdue'] > 0)
                        <p class="text-sm font-medium text-red-700 dark:text-red-300">@moneyWhole($receivables['overdue']) overdue</p>
                    @endif
                    @if ($receivables['total'] > 0)
                        <div class="mt-3 flex h-3.5 gap-0.5 overflow-hidden rounded" role="img"
                            aria-label="{{ collect($receivables['buckets'])->map(fn ($b) => $b['label'].' '.\App\Support\Money::whole($b['amount']))->join(', ') }}">
                            @foreach ($receivables['buckets'] as $i => $b)
                                @if ($b['amount'] > 0)
                                    <span class="{{ $ageing[$i] }}" style="flex: {{ $b['amount'] }} 1 0%" title="{{ $b['label'] }}: {{ \App\Support\Money::whole($b['amount']) }}"></span>
                                @endif
                            @endforeach
                        </div>
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($receivables['buckets'] as $i => $b)
                                <li class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 flex-none rounded-sm {{ $ageing[$i] }}" aria-hidden="true"></span>
                                    <span class="flex-1 text-gray-700 dark:text-gray-300">{{ $b['label'] }}{{ $i > 0 ? ' late' : '' }}</span>
                                    <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">@moneyWhole($b['amount'])</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($receivables['late'])
                            <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">Most overdue</h3>
                            <ul class="mt-1 divide-y divide-gray-100 text-sm dark:divide-gray-700">
                                @foreach ($receivables['late'] as $c)
                                    @php
                                        $url = config('mybooks.features.statements') && $user->can('view customers')
                                            ? route('customers.statement', $c['customer_id'])
                                            : ($user->can('view customers') ? route('customers.show', $c['customer_id']) : null);
                                    @endphp
                                    <li class="flex items-center gap-3 py-2">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-gray-900 dark:text-gray-100">{{ $c['name'] }}</span>
                                            <span class="block text-xs text-gray-600 dark:text-gray-400">{{ $c['invoices'] === 1 ? '1 invoice' : $c['invoices'].' invoices' }} · oldest {{ $c['days'] }} {{ $c['days'] === 1 ? 'day' : 'days' }} late</span>
                                        </span>
                                        <span class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">@moneyWhole($c['owed'])</span>
                                        @if ($url)
                                            <a href="{{ $url }}" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Remind<span class="sr-only"> {{ $c['name'] }}</span></a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No customer owes you anything right now.</p>
                    @endif
                </x-dashboard.card>
            @endif

            @if ($payables)
                <x-dashboard.card title="You owe" subtitle="Bills to pay, soonest first" id="dash-payables"
                    :link="$user->can('view bills') ? route('bills.index') : null" link-text="All bills">
                    <p class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">@moneyWhole($payables['total'])</p>
                    @if ($payables['overdue'] > 0 || $payables['week'] > 0)
                        <p class="text-sm">
                            @if ($payables['overdue'] > 0)<span class="font-medium text-red-700 dark:text-red-300">@moneyWhole($payables['overdue']) overdue</span>@endif
                            @if ($payables['overdue'] > 0 && $payables['week'] > 0)<span class="text-gray-500 dark:text-gray-400"> · </span>@endif
                            @if ($payables['week'] > 0)<span class="text-gray-700 dark:text-gray-300">@moneyWhole($payables['week']) due this week</span>@endif
                        </p>
                    @endif
                    @if ($payables['next'])
                        <ul class="mt-3 divide-y divide-gray-100 text-sm dark:divide-gray-700">
                            @foreach ($payables['next'] as $bill)
                                <li class="flex items-center gap-3 py-2">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-gray-900 dark:text-gray-100">{{ $bill['name'] }}</span>
                                        <span class="block text-xs {{ $bill['daysLate'] > 0 ? 'font-medium text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-400' }}">
                                            @if ($bill['daysLate'] > 0) Overdue {{ $bill['daysLate'] }} {{ $bill['daysLate'] === 1 ? 'day' : 'days' }}
                                            @elseif ($bill['due']) Due {{ \Illuminate\Support\Carbon::parse($bill['due'])->format('D j M') }}
                                            @endif
                                        </span>
                                    </span>
                                    <span class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">@moneyWhole($bill['amount'])</span>
                                    @can('create payments-made')
                                        <a href="{{ route('payments-made.create', ['bill_id' => $bill['id']]) }}" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Pay<span class="sr-only"> {{ $bill['name'] }}</span></a>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No unpaid bills.</p>
                    @endif
                </x-dashboard.card>
            @endif
        </div>
    @endif

    @if ($bottomRow)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @if ($customers)
                <x-dashboard.card title="Top customers" subtitle="Sales before VAT, last 12 months" id="dash-customers"
                    :link="$user->can('view reports') ? route('reports.sales-by-customer', ['start_date' => now()->subMonthsNoOverflow(11)->startOfMonth()->toDateString(), 'end_date' => now()->toDateString()]) : null" link-text="Sales by customer">
                    @if ($customers['rows'])
                        <x-dashboard.bars :rows="$customers['rows']" tone="income" />
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">Your top customers appear after your first invoices.</p>
                    @endif
                </x-dashboard.card>
            @endif

            @if ($spend)
                <x-dashboard.card title="Where the money goes" subtitle="Expenses, last 12 months" id="dash-spend"
                    :link="$user->can('view reports') ? route('reports.profit-loss', ['start_date' => now()->subMonthsNoOverflow(11)->startOfMonth()->toDateString(), 'end_date' => now()->toDateString()]) : null" link-text="Profit and loss">
                    @if ($spend['rows'])
                        <x-dashboard.bars :rows="$spend['rows']" tone="expense" />
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">Your spending appears after your first expense or bill.</p>
                    @endif
                </x-dashboard.card>
            @endif

            @if ($recent !== null)
                <x-dashboard.card title="Recent activity" subtitle="The last things recorded" id="dash-recent"
                    :link="$user->can('view activity-logs') ? route('activity-logs.index') : null" link-text="See all">
                    @if ($recent)
                        <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                            @foreach ($recent as $r)
                                <li>
                                    <a href="{{ $r['url'] }}" class="-mx-2 flex items-center gap-3 rounded px-2 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-gray-900 dark:text-gray-100">{{ $r['text'] }}</span>
                                            <span class="block text-xs text-gray-600 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($r['at'])->diffForHumans() }}</span>
                                        </span>
                                        <span class="tabular-nums font-medium text-gray-900 dark:text-gray-100">@moneyWhole($r['amount'])</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">Nothing recorded yet.</p>
                    @endif
                </x-dashboard.card>
            @endif
        </div>
    @endif
</div>
