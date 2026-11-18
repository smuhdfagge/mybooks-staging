{{-- Accountant partner programme (Nigeria marketing plan, channel 1). Plain, checked claims only: the
     role, imports and reports below exist in MyBooks; partner terms are agreed with each practice. --}}
@php
    $tick = '<svg class="h-5 w-5 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
    $apply = route('contact', ['subject' => 'partnership']).'#contact-form';
@endphp
<x-public.layout title="MyBooks for Accountants" description="Partner with MyBooks: put your clients on Nigerian accounting software with VAT, WHT and PAYE built in, work in their books with your own login, and get help moving them in.">

    <section class="bg-brand-50 px-4 pb-14 pt-14 dark:bg-gray-900 sm:px-6 lg:px-8 lg:pt-20">
        <div class="mx-auto max-w-6xl">
            <p class="text-sm font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300">Partner programme</p>
            <h1 class="mt-2 font-serif text-4xl font-semibold text-gray-900 dark:text-white lg:text-5xl">MyBooks for accountants</h1>
            <p class="mt-4 max-w-2xl text-xl text-gray-600 dark:text-gray-300">Put your clients on accounting software that does Nigerian VAT, withholding tax and PAYE the way you would, and spend month end reviewing instead of chasing receipts.</p>
            <div class="mt-8 flex flex-col gap-4 sm:flex-row">
                <a href="{{ $apply }}" class="rounded-lg bg-brand-600 px-8 py-4 text-center font-semibold text-white shadow-lg hover:bg-brand-700 focus-ring">Become a partner</a>
                <a href="{{ route('contact', ['subject' => 'demo']) }}#contact-form" class="rounded-lg border-2 border-brand-600 px-8 py-4 text-center font-semibold text-brand-700 hover:bg-brand-100 focus-ring dark:border-brand-300 dark:text-brand-300 dark:hover:bg-gray-800">Book a demo</a>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-16 dark:bg-gray-900 sm:px-6 lg:px-8 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <h2 class="font-serif text-3xl font-semibold text-gray-900 dark:text-white">Why your clients' books belong on MyBooks</h2>
            <div class="mt-10 grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl bg-gray-50 p-7 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nigerian tax done in the books</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">VAT returns laid out like the form, withholding tax grouped by the authority it is owed to, and payroll on the 2026 PAYE bands with pension, NHF and rent relief.</p>
                </div>
                <div class="rounded-2xl bg-gray-50 p-7 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Your own login, your own role</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">Your client adds you as a user with the Accountant role: the ledgers, journals, reports and payroll. Their staff see only what their roles allow.</p>
                </div>
                <div class="rounded-2xl bg-gray-50 p-7 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">The reports you work from</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">Trial balance, general ledger, profit and loss, balance sheet and cash flow, with comparatives, plus payroll and tax reports: 28 in all, exported when you need them.</p>
                </div>
                <div class="rounded-2xl bg-gray-50 p-7 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">A clean start from Excel</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">Customers, items, invoices, bills, journals and opening balances import from a spreadsheet, so a client moves over with their history, not a blank page.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-gray-50 px-4 py-16 dark:bg-gray-800 sm:px-6 lg:px-8 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <h2 class="font-serif text-3xl font-semibold text-gray-900 dark:text-white">What partners get</h2>
            <ul class="mt-10 grid gap-8 md:grid-cols-2">
                <li class="flex items-start gap-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30">{!! $tick !!}</span>
                    <div><h3 class="font-semibold text-gray-900 dark:text-white">Help moving clients in</h3><p class="mt-1 text-gray-600 dark:text-gray-300">We set up each client's business and import their records for them, with you.</p></div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30">{!! $tick !!}</span>
                    <div><h3 class="font-semibold text-gray-900 dark:text-white">Training for your team</h3><p class="mt-1 text-gray-600 dark:text-gray-300">A walkthrough for you and your staff, so your practice can support clients day to day.</p></div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30">{!! $tick !!}</span>
                    <div><h3 class="font-semibold text-gray-900 dark:text-white">A direct line to us</h3><p class="mt-1 text-gray-600 dark:text-gray-300">Partners reach the MyBooks team directly when a client needs help.</p></div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30">{!! $tick !!}</span>
                    <div><h3 class="font-semibold text-gray-900 dark:text-white">Partner terms</h3><p class="mt-1 text-gray-600 dark:text-gray-300">A referral commission, or a discount for the clients you bring: we agree the terms with each practice.</p></div>
                </li>
            </ul>
        </div>
    </section>

    <section class="bg-white px-4 py-16 dark:bg-gray-900 sm:px-6 lg:px-8 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <h2 class="font-serif text-3xl font-semibold text-gray-900 dark:text-white">How it works</h2>
            <ol class="mt-10 grid gap-6 md:grid-cols-3">
                <li class="rounded-2xl border border-gray-200 p-7 dark:border-gray-700">
                    <span class="text-sm font-semibold text-brand-700 dark:text-brand-300">Step 1</span>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Tell us about your practice</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">How many clients you keep books for, and what they do. We reply to agree terms and book a walkthrough.</p>
                </li>
                <li class="rounded-2xl border border-gray-200 p-7 dark:border-gray-700">
                    <span class="text-sm font-semibold text-brand-700 dark:text-brand-300">Step 2</span>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Move your first clients in</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">Each client gets their own business on MyBooks, with their records imported and their plan priced in naira.</p>
                </li>
                <li class="rounded-2xl border border-gray-200 p-7 dark:border-gray-700">
                    <span class="text-sm font-semibold text-brand-700 dark:text-brand-300">Step 3</span>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Work in their books</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">Sign in with your Accountant role, review the month, run payroll, post your adjustments and file from the figures.</p>
                </li>
            </ol>
            <p class="mt-8 text-gray-600 dark:text-gray-300">New to the 2026 rules? Our guides cover <a href="{{ route('guides.show', 'nigeria-tax-act-small-business') }}" class="font-medium text-brand-600 underline dark:text-brand-300">what the Nigeria Tax Act changed</a>, <a href="{{ route('guides.show', 'paye-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">PAYE</a> and <a href="{{ route('guides.show', 'nigeria-tax-calendar') }}" class="font-medium text-brand-600 underline dark:text-brand-300">every filing date</a>.</p>
        </div>
    </section>

    <section class="bg-brand-900 px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="mx-auto max-w-3xl text-center">
            <h2 class="font-serif text-3xl font-semibold text-white lg:text-4xl">Bring your clients with you</h2>
            <p class="mt-4 text-lg text-brand-100">Tell us about your practice and we will be in touch to agree terms.</p>
            <div class="mt-8 flex flex-col justify-center gap-4 sm:flex-row">
                <a href="{{ $apply }}" class="rounded-lg bg-white px-8 py-4 font-semibold text-brand-700 shadow-lg hover:bg-gray-100 focus-ring">Become a partner</a>
                <a href="{{ route('pricing') }}" class="rounded-lg border-2 border-white px-8 py-4 font-semibold text-white hover:bg-white/10 focus-ring">See client prices</a>
            </div>
        </div>
    </section>
</x-public.layout>
