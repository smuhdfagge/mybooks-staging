{{--
    The plans, side by side in one frame, with a monthly / yearly switch
    (home page plan H2). Every name, price, limit and list comes from the
    plans table through App\Support\PublicSite\PublicPlans; nothing is typed
    in here. SMS and WhatsApp allowances show only while those messages are
    really sent (decision 12).

    <x-public.pricing highlight="professional" highlight-label="Best for a growing business" />
--}}
@props(['highlight' => null, 'highlightLabel' => null])

@php
    $publicPlans = app(\App\Support\PublicSite\PublicPlans::class);
    $live = app(\App\Support\PublicSite\LiveFeatures::class);
    $plans = $publicPlans->all();
    $naira = fn (float $n) => \App\Support\PublicSite\PublicPlans::naira($n);
    $hasMonthly = $publicPlans->anyMonthly($plans);
    $hasAnnual = $publicPlans->anyAnnual($plans);
    $startYearly = $hasAnnual;
    $cols = match ($plans->count()) { 1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', default => 'md:grid-cols-3' };
@endphp

@if ($plans->isEmpty())
    <p {{ $attributes->class('text-gray-700 dark:text-gray-300') }}>Plans are being updated. Please <a href="{{ route('contact') }}" class="font-medium text-brand-600 underline dark:text-brand-300">contact us</a> for prices.</p>
@else
<div {{ $attributes }} x-data="{ yearly: @js($startYearly) }" data-pricing>
    @if ($hasMonthly && $hasAnnual)
        <div class="mb-6 inline-flex rounded-lg border border-gray-300 bg-white p-1 dark:border-gray-600 dark:bg-gray-800" role="group" aria-label="How often you pay">
            <button type="button" @click="yearly = false" :aria-pressed="(!yearly).toString()" aria-pressed="{{ $startYearly ? 'false' : 'true' }}"
                class="rounded-md px-4 py-2 text-sm font-semibold focus-ring"
                :class="!yearly ? 'bg-brand-600 text-white' : 'text-gray-700 dark:text-gray-200'">Monthly</button>
            <button type="button" @click="yearly = true" :aria-pressed="yearly.toString()" aria-pressed="{{ $startYearly ? 'true' : 'false' }}"
                class="rounded-md px-4 py-2 text-sm font-semibold focus-ring"
                :class="yearly ? 'bg-brand-600 text-white' : 'text-gray-700 dark:text-gray-200'">Yearly</button>
        </div>
    @endif

    <div class="grid grid-cols-1 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 {{ $cols }}">
        @foreach ($plans as $plan)
            @php
                $pick = $highlight !== null && $plan['slug'] === $highlight;
                // What each switch position shows. A plan without that cycle falls back to the one it has.
                $m = $plan['monthly'] !== null
                    ? ['amount' => $naira($plan['monthly']), 'per' => 'a month', 'url' => $plan['monthly_url']]
                    : ['amount' => $naira($plan['annual']), 'per' => 'a year, paid yearly only', 'url' => $plan['annual_url']];
                $y = $plan['annual'] !== null
                    ? ['amount' => $naira($plan['annual']), 'per' => 'a year ('.$naira(round($plan['annual_per_month'])).' a month)', 'url' => $plan['annual_url']]
                    : ['amount' => $naira($plan['monthly']), 'per' => 'a month, paid monthly only', 'url' => $plan['monthly_url']];
                $start = $startYearly ? $y : $m;
                $messages = [];
                if ($live->sms() && $plan['sms'] > 0) { $messages[] = number_format($plan['sms']).' SMS'; }
                if ($live->whatsapp() && $plan['whatsapp'] > 0) { $messages[] = number_format($plan['whatsapp']).' WhatsApp messages'; }
            @endphp
            <div @class([
                'flex flex-col p-6 sm:p-8',
                'border-t border-gray-200 dark:border-gray-700 md:border-l md:border-t-0' => ! $loop->first,
                'bg-brand-50 shadow-[inset_0_4px_0_theme(colors.accent.500)] dark:bg-brand-950/40' => $pick,
            ]) data-plan="{{ $plan['slug'] }}">
                @if ($pick && $highlightLabel)
                    <p class="mb-2 min-h-[1.3em] text-[0.82rem] font-semibold text-accent-700 dark:text-accent-300">{{ $highlightLabel }}</p>
                @elseif ($highlightLabel)
                    {{-- Keeps the prices in line with the highlighted plan's. --}}
                    <p class="mb-2 hidden min-h-[1.3em] text-[0.82rem] md:block" aria-hidden="true"></p>
                @endif
                <h3 class="font-serif text-[1.35rem] font-semibold text-gray-900 dark:text-white">{{ $plan['name'] }}</h3>
                @if ($plan['description'])
                    <p class="mb-5 mt-1 text-[0.95rem] text-gray-600 dark:text-gray-300 md:min-h-[3em]">{{ $plan['description'] }}</p>
                @endif

                <p class="text-[2.2rem] font-semibold leading-tight tabular-nums text-gray-900 dark:text-white"
                    x-text="yearly ? @js($y['amount']) : @js($m['amount'])">{{ $start['amount'] }}</p>
                <p class="mb-5 mt-1 text-[0.92rem] text-gray-600 dark:text-gray-300 md:min-h-[2.8em]"
                    x-text="yearly ? @js($y['per']) : @js($m['per'])">{{ $start['per'] }}</p>

                <a href="{{ $start['url'] }}" :href="yearly ? @js($y['url']) : @js($m['url'])"
                    @class([
                        'mb-6 inline-flex min-h-[44px] w-full items-center justify-center rounded-lg px-4 font-semibold focus-ring',
                        'bg-brand-600 text-white hover:bg-brand-700' => $pick,
                        'border border-gray-300 bg-white text-gray-900 hover:border-brand-600 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:hover:border-brand-300' => ! $pick,
                    ])>Choose {{ $plan['name'] }}</a>

                <ul class="space-y-2.5 text-[0.95rem] text-gray-800 dark:text-gray-200">
                    @foreach ($plan['features'] as $feature)
                        <li class="flex gap-2.5"><svg class="mt-1 h-4 w-4 shrink-0 text-green-700 dark:text-green-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 8.5 3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg><span>{{ $feature }}</span></li>
                    @endforeach
                    @if ($messages)
                        <li class="flex gap-2.5"><svg class="mt-1 h-4 w-4 shrink-0 text-green-700 dark:text-green-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 8.5 3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg><span>{{ implode(' and ', $messages) }} a month</span></li>
                    @endif
                </ul>
            </div>
        @endforeach
    </div>
    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Prices in naira. Pay through Paystack.</p>
</div>
@endif
