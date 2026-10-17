{{-- About MyBooks (home page plan H4). Plain, checked claims only; the user limit comes from the plans table. --}}
@php
    $maxUsers = (int) app(\App\Support\PublicSite\PublicPlans::class)->all()->max('users');
    $tick = '<svg class="h-5 w-5 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
@endphp
<x-public.layout title="About MyBooks" description="MyBooks is accounting software for Nigerian businesses: invoices, stock, payroll, VAT, WHT and PAYE, with prices in naira.">

    <section class="bg-brand-50 px-4 pb-14 pt-14 dark:bg-gray-900 sm:px-6 lg:px-8 lg:pt-20">
        <div class="mx-auto max-w-6xl">
            <h1 class="font-serif text-4xl font-semibold text-gray-900 dark:text-white lg:text-5xl">About MyBooks</h1>
            <p class="mt-4 max-w-2xl text-xl text-gray-600 dark:text-gray-300">Accounting software for Nigerian businesses, run from our office in Kano.</p>
        </div>
    </section>

    <section class="bg-white px-4 py-16 dark:bg-gray-900 sm:px-6 lg:px-8 lg:py-20">
        <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-2">
            <div>
                <h2 class="font-serif text-3xl font-semibold text-gray-900 dark:text-white">What MyBooks is for</h2>
                <div class="mt-6 space-y-4 text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                    <p>A lot of accounting software is made for other countries. It is priced in dollars and leaves Nigerian tax for you to work out by hand.</p>
                    <p>MyBooks keeps the whole job in one place: invoices and stock, purchases and payroll, Nigerian VAT, WHT, PAYE and pension, and the reports your accountant needs.</p>
                    <p>It works in the browser on a computer or a phone, so you can check what you are owed wherever you are.</p>
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-4 rounded-2xl bg-brand-100 p-6 dark:bg-brand-900/30 sm:gap-6 sm:p-8">
                <div class="flex flex-col-reverse rounded-xl bg-white p-5 text-center shadow-sm dark:bg-gray-800">
                    <dt class="mt-1 text-sm text-gray-600 dark:text-gray-300">Prices and figures in naira</dt>
                    <dd class="text-3xl font-semibold text-brand-600 dark:text-brand-300">₦</dd>
                </div>
                <div class="flex flex-col-reverse rounded-xl bg-white p-5 text-center shadow-sm dark:bg-gray-800">
                    <dt class="mt-1 text-sm text-gray-600 dark:text-gray-300">Nigerian tax built in</dt>
                    <dd class="text-2xl font-semibold text-brand-600 dark:text-brand-300">VAT, WHT, PAYE</dd>
                </div>
                <div class="flex flex-col-reverse rounded-xl bg-white p-5 text-center shadow-sm dark:bg-gray-800">
                    <dt class="mt-1 text-sm text-gray-600 dark:text-gray-300">Financial and payroll reports</dt>
                    <dd class="text-3xl font-semibold text-brand-600 dark:text-brand-300">28</dd>
                </div>
                @if ($maxUsers > 1)
                    <div class="flex flex-col-reverse rounded-xl bg-white p-5 text-center shadow-sm dark:bg-gray-800">
                        <dt class="mt-1 text-sm text-gray-600 dark:text-gray-300">People on one account</dt>
                        <dd class="text-3xl font-semibold text-brand-600 dark:text-brand-300">Up to {{ $maxUsers }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>

    <section class="bg-gray-50 px-4 py-16 dark:bg-gray-800 sm:px-6 lg:px-8 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <h2 class="font-serif text-3xl font-semibold text-gray-900 dark:text-white">How we build it</h2>
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <div class="rounded-2xl bg-white p-7 shadow-sm dark:bg-gray-900">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Plain words</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">Screens say what they do, so daily work does not need an accountant at your side.</p>
                </div>
                <div class="rounded-2xl bg-white p-7 shadow-sm dark:bg-gray-900">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nigerian rules first</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">VAT, withholding tax, PAYE and pension are set up from day one, not added on later.</p>
                </div>
                <div class="rounded-2xl bg-white p-7 shadow-sm dark:bg-gray-900">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Your books stay yours</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">Records travel over an encrypted connection, each person sees only what their role allows, and you can download a full backup any time.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-16 dark:bg-gray-900 sm:px-6 lg:px-8 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <h2 class="font-serif text-3xl font-semibold text-gray-900 dark:text-white">Why businesses choose MyBooks</h2>
            <ul class="mt-10 grid gap-8 md:grid-cols-2">
                <li class="flex items-start gap-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30">{!! $tick !!}</span>
                    <div><h3 class="font-semibold text-gray-900 dark:text-white">Everything in one place</h3><p class="mt-1 text-gray-600 dark:text-gray-300">Sales, purchases, stock, payroll and reports share one set of books.</p></div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30">{!! $tick !!}</span>
                    <div><h3 class="font-semibold text-gray-900 dark:text-white">Easy to use</h3><p class="mt-1 text-gray-600 dark:text-gray-300">Plain words and clear screens, on a computer or a phone.</p></div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30">{!! $tick !!}</span>
                    <div><h3 class="font-semibold text-gray-900 dark:text-white">Prices in naira</h3><p class="mt-1 text-gray-600 dark:text-gray-300">Plans priced in naira and paid through Paystack.</p></div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30">{!! $tick !!}</span>
                    <div><h3 class="font-semibold text-gray-900 dark:text-white">Help when you need it</h3><p class="mt-1 text-gray-600 dark:text-gray-300">Reach us by email or phone, or <a href="{{ route('contact', ['subject' => 'demo']) }}#contact-form" class="font-medium text-brand-600 underline dark:text-brand-300">book a demo</a>.</p></div>
                </li>
            </ul>
        </div>
    </section>

    <section class="bg-brand-900 px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="mx-auto max-w-3xl text-center">
            <h2 class="font-serif text-3xl font-semibold text-white lg:text-4xl">Ready to put your books in order?</h2>
            <p class="mt-4 text-lg text-brand-100">Prices in naira. Pay through Paystack.</p>
            <div class="mt-8 flex flex-col justify-center gap-4 sm:flex-row">
                <a href="{{ route('home') }}#pricing" class="rounded-lg bg-white px-8 py-4 font-semibold text-brand-700 shadow-lg hover:bg-gray-100 focus-ring">Choose a plan</a>
                <a href="{{ route('contact', ['subject' => 'demo']) }}#contact-form" class="rounded-lg border-2 border-white px-8 py-4 font-semibold text-white hover:bg-white/10 focus-ring">Book a demo</a>
            </div>
        </div>
    </section>
</x-public.layout>
