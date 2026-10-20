{{--
    What MyBooks costs, as its own page (SEO plan). The home page carries
    the same plans, but a section of it cannot be found on its own, and
    "mybooks pricing" is a search people make by name.

    Every figure comes from the plans table, through the shared pricing
    component. Nothing is typed in here.
--}}
@php
    $publicPlans = app(\App\Support\PublicSite\PublicPlans::class);
    $plans = $publicPlans->all();
    $naira = fn (float $n) => \App\Support\PublicSite\PublicPlans::naira($n);

    // The cheapest way in, said plainly in the opening line.
    // Rounded to whole naira, as the plan cards show it.
    $cheapest = $plans->map(fn ($p) => $p['annual_per_month'] ?? $p['monthly'])->filter()->min();
    $cheapest = $cheapest !== null ? round((float) $cheapest) : null;

    // "Yearly costs the same as ten months", only when that holds of every
    // plan offering both.
    $both = $plans->filter(fn ($p) => $p['monthly'] !== null && $p['annual'] !== null);
    $months = $both->map(fn ($p) => round($p['annual'] / $p['monthly'], 2))->unique();
    $yearlyNote = $both->isNotEmpty() && $months->count() === 1 && fmod((float) $months->first(), 1.0) === 0.0 && $months->first() < 12
        ? 'Paying yearly costs the same as '.\Illuminate\Support\Number::spell((int) $months->first()).' months.'
        : null;

    $maxUsers = (int) $plans->max('users');
    $autoRenew = config('mybooks.features.auto_renewal');

    $faq = [
        ['Can I try MyBooks before I pay?', 'Book a demo and we will walk you through MyBooks and answer your questions before you choose a plan.'],
        ['How do I pay?', 'Through Paystack, with a card or a bank transfer, in naira.'.($autoRenew ? ' You can save a card so your plan renews by itself, or pay each time.' : '')],
        ['Can I change plan later?', 'Yes. Move up or down whenever you like; the new plan starts from your next payment.'],
        ['Are the prices in naira?', 'Yes. Every price on this page is in naira, and that is what you are charged.'],
        ['What happens if I stop paying?', 'Your records stay yours. Download a full backup of your books before you go.'],
        ['Is there a charge per user?', $maxUsers > 0 ? 'No. Each plan includes a number of users, up to '.$maxUsers.' on the largest plan, and you are not billed per person.' : 'No. Users are included in the plan.'],
    ];

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($q) => [
            '@type' => 'Question',
            'name' => $q[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($q[1])],
        ], $faq),
    ];
@endphp

<x-public.layout
    title="Pricing in naira"
    description="See what MyBooks costs per month and per year, in naira. Compare plans, user limits and features, and pick the one that fits your business."
    :schema="$schema">

    <section class="mx-auto max-w-6xl px-4 pb-6 pt-14 sm:px-6 sm:pt-20">
        <h1 class="max-w-3xl text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">
            MyBooks pricing, in naira
        </h1>
        <p class="mt-4 max-w-2xl text-lg text-gray-700 dark:text-gray-300">
            @if ($cheapest)
                Accounting built for Nigerian businesses, from {{ $naira((float) $cheapest) }} a month.
            @else
                Accounting built for Nigerian businesses.
            @endif
            No charge per user, no setup fee, and you can leave with your books whenever you like.
        </p>
        @if ($yearlyNote)
            <p class="mt-2 max-w-2xl text-gray-700 dark:text-gray-300">{{ $yearlyNote }}</p>
        @endif
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6" aria-label="Plans and prices">
        <x-public.pricing highlight="professional" highlight-label="Best for a growing business" />
    </section>

    {{-- What every plan includes, so the table above is not the whole story. --}}
    <section class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">In every plan</h2>
            <ul class="mt-6 grid gap-x-8 gap-y-3 text-gray-700 sm:grid-cols-2 lg:grid-cols-3 dark:text-gray-300">
                @foreach ([
                    'Invoices and quotations in naira, with your own logo',
                    'VAT at 7.5% on invoices and bills, and the monthly return filled in from them',
                    'Withholding tax taken off supplier payments and grouped by authority',
                    'Customers, suppliers, items and stock',
                    'Bank accounts, reconciliation and cash book',
                    'Profit and loss, balance sheet, trial balance and cash flow',
                    'Your accountant added as a user, free',
                    'Works in your phone\'s browser',
                    'Your data exported in full whenever you ask',
                ] as $included)
                    <li class="flex gap-2">
                        <svg class="mt-1 h-4 w-4 shrink-0 text-brand-600 dark:text-brand-300" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2"/></svg>
                        <span>{{ $included }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6" aria-label="Questions about pricing">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Questions about paying</h2>
        <dl class="mt-8 space-y-6">
            @foreach ($faq as [$question, $answer])
                <div class="border-b border-gray-200 pb-6 last:border-0 dark:border-gray-800">
                    <dt class="font-semibold text-gray-900 dark:text-white">{{ $question }}</dt>
                    <dd class="mt-2 text-gray-700 dark:text-gray-300">{{ $answer }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="border-t border-gray-200 dark:border-gray-800">
        <div class="mx-auto max-w-3xl px-4 py-14 text-center sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Not sure which plan?</h2>
            <p class="mt-3 text-gray-700 dark:text-gray-300">
                Book a demo and we will go through MyBooks with you, then tell you which plan fits.
            </p>
            <a href="{{ route('contact', ['subject' => 'demo']) }}#contact-form" class="mt-6 inline-flex items-center rounded-lg bg-brand-600 px-5 py-3 font-semibold text-white hover:bg-brand-700 focus-ring">
                Book a demo
            </a>
        </div>
    </section>
</x-public.layout>
