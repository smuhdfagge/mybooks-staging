{{--
    Dashboard (dashboard upgrade): how the business is doing at a glance.
    Figures: App\Services\Dashboard\DashboardService (same ledger as the
    reports). Cards and the permission for each: config/dashboard.php.
    The lower cards load just after the page: livewire:dashboard.lower-cards.
--}}
@php
    $user = auth()->user();
    $show = fn (string $widget) => $user->can("{$widget} dashboard-widgets");
    $tiles = array_values(array_filter(['cash-position', 'total-revenue', 'monthly-expenses', 'profit'], $show));
    $series = collect($months);
    $against = $period->compareLabel();
    $bookLock = \App\Services\Accounting\LockDates::instance()->dates((int) $user->tenant_id);
    $hasAttention = $attention !== null;
    $hasChart = $show('revenue-chart') && $series->isNotEmpty();
    $chartHasData = $series->sum(fn ($m) => abs($m['income']) + abs($m['expenses'])) > 0;
    $pl = fn () => route('reports.profit-loss', ['start_date' => $period->from->toDateString(), 'end_date' => $period->to->toDateString()]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="space-y-4 sm:space-y-5">
        {{-- Header: greeting, period, + New --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-white">{{ $greeting }}, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $user->tenant?->name }} · how the business is doing</p>
                @if ($bookLock['staff'])
                    {{-- Lock dates (session 11) --}}
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" data-books-locked>
                        <svg class="inline w-4 h-4 -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Books locked up to {{ $bookLock['staff']->format('j M Y') }}@if ($bookLock['all_users']) (for everyone up to {{ $bookLock['all_users']->format('j M Y') }})@endif.
                        @can('view chart-of-accounts')
                            <a href="{{ route('accounting-periods.index') }}#lock-dates" class="text-brand-700 dark:text-brand-300 hover:underline">Lock dates</a>
                        @endcan
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($summary !== null || $hasChart)
                    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2" x-data>
                        <label for="dash-period" class="sr-only">Period</label>
                        <select id="dash-period" name="period" x-on:change="$el.form.submit()" class="h-9 rounded-md border-gray-300 bg-white py-0 pl-3 pr-8 text-sm font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            @foreach (\App\Services\Dashboard\DashboardPeriod::PERIODS as $key => $text)
                                <option value="{{ $key }}" @selected($period->key === $key)>{{ $period->key === $key ? $period->label() : $text }}</option>
                            @endforeach
                        </select>
                        <label for="dash-compare" class="sr-only">Compare with</label>
                        <select id="dash-compare" name="compare" x-on:change="$el.form.submit()" class="h-9 rounded-md border-gray-300 bg-white py-0 pl-3 pr-8 text-sm font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            @foreach (\App\Services\Dashboard\DashboardPeriod::COMPARES as $key => $text)
                                <option value="{{ $key }}" @selected($period->compareKey === $key)>Compare: {{ strtolower($text) }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="btn-secondary h-9">Show</button></noscript>
                    </form>
                @endif

                @if ($newMenu)
                    <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
                        <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true" aria-controls="dash-new-menu"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md bg-brand-600 px-3.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                            New
                        </button>
                        <div id="dash-new-menu" x-show="open" x-transition.opacity style="display: none" class="absolute right-0 z-20 mt-1 w-52 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            @foreach ($newMenu as $item)
                                <a href="{{ $item['url'] }}" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700">{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Getting started (new businesses) --}}
        @if ($gettingStarted)
            @php $done = collect($gettingStarted)->where('done', true)->count(); @endphp
            <x-dashboard.card title="Getting started" :subtitle="$done.' of '.count($gettingStarted).' done'" id="dash-start">
                <x-slot name="actions">
                    <form method="POST" action="{{ route('dashboard.getting-started.hide') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900 hover:underline dark:text-gray-400 dark:hover:text-white">Hide</button>
                    </form>
                </x-slot>
                <div class="mb-3 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                    <div class="h-full rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ round($done / count($gettingStarted) * 100) }}%"></div>
                </div>
                <ol class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($gettingStarted as $step)
                        <li>
                            <a href="{{ $step['url'] }}" class="flex items-center gap-3 rounded-md border px-3 py-2.5 text-sm {{ $step['done'] ? 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-400' : 'border-gray-300 text-gray-900 hover:border-brand-400 hover:bg-brand-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700' }}">
                                @if ($step['done'])
                                    <svg class="h-5 w-5 flex-none text-green-700 dark:text-green-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                                    <span class="line-through decoration-gray-400">{{ $step['label'] }}</span><span class="sr-only"> (done)</span>
                                @else
                                    <span class="h-5 w-5 flex-none rounded-full border-2 border-gray-300 dark:border-gray-500" aria-hidden="true"></span>
                                    <span>{{ $step['label'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ol>
            </x-dashboard.card>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- The four main cards --}}
            @if ($tiles)
                <div class="lg:col-span-3 grid grid-cols-2 gap-3 sm:gap-4 {{ count($tiles) >= 4 ? 'xl:grid-cols-4' : (count($tiles) === 3 ? 'xl:grid-cols-3' : '') }}">
                    @foreach ($tiles as $tile)
                        @php
                            $spark = $series->pluck(match ($tile) { 'cash-position' => 'cash', 'total-revenue' => 'income', 'monthly-expenses' => 'expenses', default => 'profit' })->all();
                            [$name, $value, $link, $tone] = match ($tile) {
                                'cash-position' => ['Cash in bank', $summary['cash'], route('reports.balance-sheet'), 'income'],
                                'total-revenue' => ['Income', $summary['income'], $pl(), 'income'],
                                'monthly-expenses' => ['Expenses', $summary['expenses'], $pl(), 'expense'],
                                default => ['Net profit', $summary['profit'], $pl(), 'profit'],
                            };
                            $canOpen = $user->can('view reports');
                            $tag = $canOpen ? 'a' : 'div';
                        @endphp
                        <{{ $tag }} @if ($canOpen) href="{{ $link }}" @endif
                            class="group flex flex-col rounded-lg border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-800 {{ $canOpen ? 'hover:border-brand-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:border-brand-600' : '' }}"
                            data-tile="{{ $tile }}">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $name }}</span>
                            <span class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight tabular-nums {{ $value < 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-900 dark:text-white' }}">{{ $value < 0 ? '−' : '' }}@moneyWhole(abs($value))</span>
                            @if ($tile === 'cash-position')
                                <x-dashboard.change :now="$summary['cash']" :before="$summary['cashBefore']" :up-is-good="true" :amount="true"
                                    :against="'since '.\Illuminate\Support\Carbon::parse($summary['cashSince'])->format('j M')" />
                            @elseif ($tile === 'profit')
                                @if ($summary['margin'] !== null)
                                    <p class="text-sm font-medium {{ $summary['margin'] >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">{{ $summary['margin'] }}% margin</p>
                                @endif
                            @else
                                <x-dashboard.change :now="$tile === 'total-revenue' ? $summary['income'] : $summary['expenses']"
                                    :before="$summary['compare'] ? ($tile === 'total-revenue' ? $summary['compare']['income'] : $summary['compare']['expenses']) : null"
                                    :up-is-good="$tile === 'total-revenue'" :against="$against" />
                            @endif
                            <span class="mt-auto flex items-end justify-between gap-3 pt-2">
                                <span class="text-xs text-gray-600 dark:text-gray-400">
                                    @switch($tile)
                                        @case('cash-position') {{ $summary['cashAccounts'] === 1 ? '1 account' : $summary['cashAccounts'].' accounts' }} @break
                                        @case('total-revenue') Invoiced in the period @break
                                        @case('monthly-expenses') Bills, expenses and payroll @break
                                        @default
                                            @if ($summary['compare']) {{ $against }}: {{ $summary['compare']['profit'] < 0 ? '−' : '' }}@moneyWhole(abs($summary['compare']['profit'])) @endif
                                    @endswitch
                                </span>
                                <x-dashboard.sparkline :values="$spark" :tone="$tone" class="hidden sm:block" />
                            </span>
                        </{{ $tag }}>
                    @endforeach
                </div>
            @endif

            {{-- Income and expenses chart --}}
            @if ($hasChart)
                @php
                    $last = $series->last();
                    $chartSubtitle = new \Illuminate\Support\HtmlString('<span class="sm:hidden">Last 6 months</span><span class="hidden sm:inline">Last 12 months</span>'.e($last['partial'] ? ' · '.$last['label'].' so far' : ''));
                    $chartData = $series->map(fn ($m) => ['label' => $m['label'], 'long' => $m['long'], 'income' => $m['income'], 'expenses' => $m['expenses'], 'profit' => $m['profit'], 'partial' => $m['partial']])->values();
                @endphp
                <x-dashboard.card title="Income and expenses" :subtitle="$chartSubtitle" id="dash-chart"
                    class="{{ $hasAttention ? 'lg:col-span-2' : 'lg:col-span-3' }}" x-data="{ table: false }">
                    <x-slot name="actions">
                        <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-xs text-gray-700 dark:text-gray-300">
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#2F6AAE] dark:bg-[#5B8FD3]"></span>Income</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#C0841A] dark:bg-[#BF8020]"></span>Expenses</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-3.5 bg-[#374151] dark:bg-[#D1D5DB]"></span>Profit</span>
                            <button type="button" x-on:click="table = !table" class="font-semibold text-brand-700 hover:underline dark:text-brand-300" :aria-pressed="table.toString()">
                                <span x-text="table ? 'Show as chart' : 'Show as table'">Show as table</span>
                            </button>
                        </div>
                    </x-slot>

                    @if ($chartHasData)
                        <div x-show="!table" class="relative h-64 sm:h-72">
                            <canvas id="dash-income-chart" role="img" aria-label="Income, expenses and profit for each of the last 12 months. Use Show as table for the figures."></canvas>
                        </div>
                    @else
                        <div x-show="!table" class="flex h-48 items-center justify-center rounded-md bg-gray-50 text-sm text-gray-600 dark:bg-gray-900/40 dark:text-gray-400">
                            Your chart appears after your first invoice or expense.
                        </div>
                    @endif
                    <div x-show="table" style="display: none" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Income, expenses and profit by month</caption>
                            <thead>
                                <tr class="text-left text-gray-600 dark:text-gray-400">
                                    <th scope="col" class="py-2 pr-4 font-medium">Month</th>
                                    <th scope="col" class="py-2 pr-4 text-right font-medium">Income</th>
                                    <th scope="col" class="py-2 pr-4 text-right font-medium">Expenses</th>
                                    <th scope="col" class="py-2 text-right font-medium">Profit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 tabular-nums">
                                @foreach ($series->reverse() as $m)
                                    <tr>
                                        <th scope="row" class="py-1.5 pr-4 text-left font-normal text-gray-800 dark:text-gray-200">{{ $m['long'] }}{{ $m['partial'] ? ' (so far)' : '' }}</th>
                                        <td class="py-1.5 pr-4 text-right text-gray-900 dark:text-gray-100">@moneyWhole($m['income'])</td>
                                        <td class="py-1.5 pr-4 text-right text-gray-900 dark:text-gray-100">@moneyWhole($m['expenses'])</td>
                                        <td class="py-1.5 text-right font-medium {{ $m['profit'] < 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-900 dark:text-gray-100' }}">{{ $m['profit'] < 0 ? '−' : '' }}@moneyWhole(abs($m['profit']))</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-dashboard.card>
            @endif

            {{-- Needs your attention (first on a phone) --}}
            @if ($hasAttention)
                <x-dashboard.card title="Needs your attention" :subtitle="count($attention) ? (count($attention) === 1 ? '1 thing' : count($attention).' things') : null" id="dash-attention"
                    class="order-first lg:order-none {{ $hasChart ? 'lg:col-start-3' : 'lg:col-span-3' }}">
                    @if ($attention)
                        <ul class="-my-1 divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($attention as $item)
                                @php
                                    [$dot, $sign, $word] = match ($item['tone']) {
                                        'bad' => ['bg-red-700', '!', 'Problem'],
                                        'warn' => ['bg-amber-700', '!', 'Coming up'],
                                        default => ['bg-brand-600', 'i', 'Information'],
                                    };
                                @endphp
                                <li>
                                    <a href="{{ $item['url'] }}" class="-mx-2 flex items-center gap-3 rounded px-2 py-2.5 text-sm hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:bg-gray-700/40">
                                        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold text-white {{ $dot }}" aria-hidden="true">{{ $sign }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="sr-only">{{ $word }}: </span>
                                            <span class="block font-semibold text-gray-900 dark:text-white">{{ $item['title'] }}</span>
                                            <span class="block text-gray-600 dark:text-gray-400">{{ $item['text'] }}</span>
                                        </span>
                                        <svg class="h-4 w-4 flex-none text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.3 14.7a1 1 0 010-1.4L10.6 10 7.3 6.7a1 1 0 011.4-1.4l4 4a1 1 0 010 1.4l-4 4a1 1 0 01-1.4 0z" clip-rule="evenodd"/></svg>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <svg class="h-5 w-5 text-green-700 dark:text-green-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                            Nothing needs your attention today.
                        </p>
                    @endif
                </x-dashboard.card>
            @endif
        </div>

        {{-- Cash flow, who owes whom, top customers, spending, recent activity --}}
        <livewire:dashboard.lower-cards lazy />

        <p class="text-xs text-gray-600 dark:text-gray-400">Figures as of {{ $updatedAt->format('j M Y, g:i a') }}. Every card opens the report behind it.</p>
    </div>

    @if ($hasChart)
        @push('scripts')
            <script nonce="{{ app('csp-nonce') }}">
                // Income and expenses chart (dashboard upgrade). Colours from
                // config/brand.php (checked for colour blindness, both modes);
                // one naira scale; the current month is drawn lighter, "so far".
                document.addEventListener('DOMContentLoaded', () => document.getElementById('dash-income-chart') && window.loadChart().then((Chart) => {
                    const canvas = document.getElementById('dash-income-chart');
                    const all = @js($chartData);
                    const colours = @js(config('brand.chart'));
                    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
                    let chart;

                    const draw = () => {
                        const dark = document.documentElement.classList.contains('dark');
                        const c = dark ? colours.dark : colours;
                        const rows = window.innerWidth < 640 ? all.slice(-6) : all;
                        const faded = (hex, partial) => partial ? hex + '73' : hex;
                        const ink = dark ? '#D1D5DB' : '#4B5563';
                        const grid = dark ? 'rgba(255,255,255,0.08)' : '#E5E7EB';
                        const surface = dark ? '#1F2937' : '#FFFFFF';
                        if (chart) chart.destroy();
                        chart = new Chart(canvas, {
                            data: {
                                labels: rows.map(r => r.label),
                                datasets: [
                                    { type: 'line', label: 'Profit', data: rows.map(r => r.profit), borderColor: c.profit, backgroundColor: c.profit, borderWidth: 2, pointRadius: 3.5, pointHoverRadius: 5, pointBorderColor: surface, pointBorderWidth: 2, tension: 0, order: 0 },
                                    { type: 'bar', label: 'Income', data: rows.map(r => r.income), backgroundColor: rows.map(r => faded(c.income, r.partial)), borderRadius: 4, borderSkipped: 'start', barPercentage: 0.7, categoryPercentage: 0.7, order: 1 },
                                    { type: 'bar', label: 'Expenses', data: rows.map(r => r.expenses), backgroundColor: rows.map(r => faded(c.expense, r.partial)), borderRadius: 4, borderSkipped: 'start', barPercentage: 0.7, categoryPercentage: 0.7, order: 1 },
                                ],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: surface, titleColor: dark ? '#F9FAFB' : '#111827', bodyColor: ink,
                                        borderColor: dark ? '#4B5563' : '#D1D5DB', borderWidth: 1, padding: 10, boxPadding: 4,
                                        itemSort: (a, b) => ['Income', 'Expenses', 'Profit'].indexOf(a.dataset.label) - ['Income', 'Expenses', 'Profit'].indexOf(b.dataset.label),
                                        callbacks: {
                                            title: (items) => { const r = rows[items[0].dataIndex]; return r.long + (r.partial ? ' (so far)' : ''); },
                                            label: (item) => ' ' + item.dataset.label + ': ' + window.formatMoney(item.raw),
                                        },
                                    },
                                },
                                scales: {
                                    x: { grid: { display: false }, border: { color: grid }, ticks: { color: ink, font: { size: 11 } } },
                                    y: {
                                        beginAtZero: true, grid: { color: grid }, border: { display: false },
                                        ticks: { color: ink, font: { size: 11 }, maxTicksLimit: 6, callback: (v) => {
                                            const a = Math.abs(v), s = v < 0 ? '−' : '';
                                            const sym = document.querySelector('meta[name="currency-symbol"]')?.content ?? '';
                                            return s + sym + (a >= 1e6 ? (a / 1e6).toLocaleString('en-US', { maximumFractionDigits: 1 }) + 'm' : a >= 1e3 ? (a / 1e3).toLocaleString('en-US', { maximumFractionDigits: 0 }) + 'k' : a);
                                        } },
                                    },
                                },
                            },
                        });
                    };

                    draw();
                    // Redraw when the theme changes or the screen crosses the phone width.
                    new MutationObserver(draw).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    let narrow = window.innerWidth < 640;
                    window.addEventListener('resize', () => { const n = window.innerWidth < 640; if (n !== narrow) { narrow = n; draw(); } });
                }));
            </script>
        @endpush
    @endif
</x-app-layout>
