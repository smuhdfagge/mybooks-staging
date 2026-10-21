{{--
    The reports feature page (SEO plan, build order 9). The lists here are
    reports that exist in resources/views/reports; nothing aspirational.
--}}
@php
    $groups = [
        ['The ones your accountant asks for', [
            'Profit and loss',
            'Balance sheet',
            'Trial balance',
            'Cash flow',
            'General ledger',
        ]],
        ['The ones that tell you how trading is going', [
            'Sales by customer',
            'Sales by item',
            'Purchases by supplier',
            'Stock on hand',
            'Money owed to you, by age',
            'Money you owe, by age',
        ]],
        ['The ones the tax office wants', [
            'VAT return, laid out as NRS Form 002',
            'Withholding tax due, grouped by authority',
            'Tax liability',
            'PAYE and payroll tax',
        ]],
    ];
@endphp

<x-public.feature-page
    :page="$page"
    heading="Reports you can act on, not just file"
    lead="Profit and loss, balance sheet, trial balance and cash flow, built from your own invoices, bills and payments. Open any of them for any period, and take it away as a PDF or a CSV.">

    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6" aria-label="The reports">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">What you get</h2>
        <div class="mt-8 space-y-10">
            @foreach ($groups as [$heading, $reports])
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $heading }}</h3>
                    <ul class="mt-4 grid gap-x-8 gap-y-3 text-gray-700 sm:grid-cols-2 dark:text-gray-300">
                        @foreach ($reports as $report)
                            <li class="flex gap-2">
                                <svg class="mt-1 h-4 w-4 shrink-0 text-brand-600 dark:text-brand-300" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2"/></svg>
                                <span>{{ $report }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40" aria-label="How the reports work">
        <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Built from your records, not typed in again</h2>
            <dl class="mt-8 space-y-5">
                @foreach ([
                    ['Any period you like', 'A month, a quarter, a year, or two periods side by side to see what changed.'],
                    ['PDF or CSV', 'Every report downloads as a PDF to send, or a CSV to work on in a spreadsheet.'],
                    ['Figures you can trace', 'A number in a report comes from documents you can open. When your accountant queries a figure, you can show them where it came from.'],
                    ['Your own reports', 'Build a report with the columns and filters you want, save it, and run it again whenever you need it.'],
                ] as [$name, $what])
                    <div class="border-b border-gray-200 pb-5 last:border-0 dark:border-gray-800">
                        <dt class="font-semibold text-gray-900 dark:text-white">{{ $name }}</dt>
                        <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $what }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6" aria-label="For your accountant">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Your accountant can look for themselves</h2>
        <p class="mt-3 max-w-2xl text-gray-700 dark:text-gray-300">
            Add your accountant as a user and they get the ledger, the reports and the month-end tools, without you
            exporting anything or emailing a spreadsheet back and forth. There is no charge per user: each plan includes
            a number of them.
        </p>
    </section>

    <x-slot:related>
        <li><a href="{{ route('features.show', 'vat-returns') }}" class="font-medium text-brand-600 underline dark:text-brand-300">VAT returns</a> — the monthly return, laid out as Form 002</li>
        <li><a href="{{ route('features.show', 'expenses') }}" class="font-medium text-brand-600 underline dark:text-brand-300">Expenses</a> — where your spending figures come from</li>
        <li><a href="{{ route('guides.show', 'vat-returns-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">How to file VAT returns in Nigeria</a></li>
    </x-slot:related>
</x-public.feature-page>
