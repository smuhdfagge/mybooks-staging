{{--
    The invoicing feature page (SEO plan, build order 6). Only things
    MyBooks really does: reminders by SMS or WhatsApp appear only while
    those messages are actually being sent (LiveFeatures).
--}}
@php
    $live = app(\App\Support\PublicSite\LiveFeatures::class);
    $channels = collect(['email'])
        ->concat($live->sms() ? ['SMS'] : [])
        ->concat($live->whatsapp() ? ['WhatsApp'] : []);
    $channelList = $channels->count() > 1
        ? $channels->slice(0, -1)->implode(', ').' or '.$channels->last()
        : $channels->first();

    $steps = [
        ['Raise it', 'Pick the customer, add the lines, and VAT at 7.5% goes on as its own line. Prices and totals in naira.'],
        ['Send it', 'Email the invoice with a PDF attached, or print it and hand it over. Either way it carries your logo.'],
        ['Record what comes in', 'Enter a payment against the invoice, including any withholding tax the customer kept back, and the balance updates itself.'],
        ['See what is owed', 'Who owes what, and how late, without going through a file of paper.'],
    ];
@endphp

<x-public.feature-page
    :page="$page"
    heading="Invoices your customers take seriously"
    lead="Send invoices in naira with your own logo and TIN, record what customers pay, and see what is still owed. It works in your phone's browser, so you can invoice from the shop or the site.">

    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6" aria-label="How it works">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">How it works</h2>
        <ol class="mt-8 space-y-6">
            @foreach ($steps as $i => [$when, $what])
                <li class="flex gap-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $when }}</h3>
                        <p class="mt-1 text-gray-700 dark:text-gray-300">{{ $what }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40" aria-label="What the invoice carries">
        <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">What goes on the invoice</h2>
            <p class="mt-3 max-w-2xl text-gray-700 dark:text-gray-300">
                An invoice is often the only thing a customer sees of your business on paper. This one looks like it came from a company.
            </p>
            <ul class="mt-6 grid gap-x-8 gap-y-3 text-gray-700 sm:grid-cols-2 dark:text-gray-300">
                @foreach ([
                    'Your logo, your colours and your lettering',
                    'Your business name, address and TIN',
                    'VAT at 7.5% shown separately from the goods',
                    'What is owed and the date it is due, at a glance',
                    'Your own notes and terms',
                    'The same document on screen, in print and as a PDF',
                ] as $line)
                    <li class="flex gap-2">
                        <svg class="mt-1 h-4 w-4 shrink-0 text-brand-600 dark:text-brand-300" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2"/></svg>
                        <span>{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-6 text-gray-700 dark:text-gray-300">
                Quotations, credit notes, delivery notes and receipts all come out of the same template, so everything your
                customer receives looks like one business.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6" aria-label="Getting paid">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Getting paid, and chasing what is late</h2>
        <dl class="mt-8 space-y-5">
            <div class="border-b border-gray-200 pb-5 dark:border-gray-800">
                <dt class="font-semibold text-gray-900 dark:text-white">Payments, including withholding tax</dt>
                <dd class="mt-1 text-gray-700 dark:text-gray-300">
                    Record what the customer actually paid and what they kept back as WHT. Both together clear the invoice,
                    and the receipt shows the customer exactly that.
                </dd>
            </div>
            <div class="border-b border-gray-200 pb-5 dark:border-gray-800">
                <dt class="font-semibold text-gray-900 dark:text-white">Reminders {{ $channelList }}</dt>
                <dd class="mt-1 text-gray-700 dark:text-gray-300">
                    Chase an overdue invoice without writing the message yourself.
                </dd>
            </div>
            <div class="border-b border-gray-200 pb-5 last:border-0 dark:border-gray-800">
                <dt class="font-semibold text-gray-900 dark:text-white">Part payments and credit notes</dt>
                <dd class="mt-1 text-gray-700 dark:text-gray-300">
                    A customer paying half, returning goods or being given credit is handled properly, not by editing the
                    original invoice.
                </dd>
            </div>
        </dl>
    </section>

    <x-slot:related>
        <li><a href="{{ route('features.show', 'vat-returns') }}" class="font-medium text-brand-600 underline dark:text-brand-300">VAT returns</a> — where the VAT on these invoices ends up</li>
        <li><a href="{{ route('guides.show', 'withholding-tax-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">Withholding tax in Nigeria</a> — why a customer pays you less than the invoice</li>
        <li><a href="{{ route('guides.show', 'e-invoicing-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">Nigeria's e-invoicing rules</a> — what is coming for invoices</li>
    </x-slot:related>
</x-public.feature-page>
