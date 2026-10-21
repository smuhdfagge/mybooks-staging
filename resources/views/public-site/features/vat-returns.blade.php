{{--
    The VAT feature page (SEO plan, build order 5). Where the VAT guide
    sends its readers. Everything claimed here is something MyBooks does:
    the Form 002 layout and its schedules come from
    App\Services\Accounting\VatReturnForm.
--}}
@php
    $live = app(\App\Support\PublicSite\LiveFeatures::class);
    $steps = [
        ['As you invoice', 'VAT at 7.5% goes on each invoice as its own line, so the customer sees goods and tax separately and your records carry both.'],
        ['As you record bills', 'The VAT you pay suppliers is recorded against each bill, which is what you take off at the end of the month.'],
        ['On the 20th', 'Open the VAT return for the month. It is already filled in from those records, laid out line by line as NRS Form 002.'],
        ['Filing', 'Download Form 002 as a PDF, and the sales schedule as a CSV in the column order the NRS upload template expects.'],
        ['Afterwards', 'Mark the return filed and settle it, and the payment is posted to your books for you.'],
    ];
@endphp

<x-public.feature-page
    :page="$page"
    heading="VAT returns, filled in from your own invoices"
    lead="MyBooks adds 7.5% VAT as you invoice and as you record bills, then lays your monthly return out as NRS Form 002 with the schedules to upload. No rebuilding the figures on the 20th.">

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

    <section class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40" aria-label="The return itself">
        <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Laid out like Form 002</h2>
            <p class="mt-3 max-w-2xl text-gray-700 dark:text-gray-300">
                Not a summary you then have to translate. The return follows the numbered lines of the
                Nigeria Revenue Service's VAT Form 002, so what you see is what you file.
            </p>
            <ul class="mt-6 grid gap-x-8 gap-y-3 text-gray-700 sm:grid-cols-2 dark:text-gray-300">
                @foreach ([
                    'Sales, exempt and zero-rated supplies separated',
                    'Output VAT and input VAT on their own lines',
                    'Imports and VAT withheld at source entered where the form asks',
                    'VAT credit brought forward and carried forward',
                    'Sales and purchases schedules behind the figures',
                    'Every line traceable back to the invoice or bill it came from',
                ] as $line)
                    <li class="flex gap-2">
                        <svg class="mt-1 h-4 w-4 shrink-0 text-brand-600 dark:text-brand-300" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2"/></svg>
                        <span>{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6" aria-label="What you can download">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">What you can take to the tax office</h2>
        <dl class="mt-8 space-y-5">
            @foreach ([
                ['Form 002 as a PDF', 'The return laid out as the form, with its schedules, ready to print or attach.'],
                ['The sales schedule as a CSV', 'In the column order the NRS upload template expects, so it can go straight up rather than being retyped.'],
                ['The purchases schedule', 'Everything behind your input VAT claim, in case a figure is queried.'],
                ['Sales adjustments', 'Credit notes and corrections, separated, because that is where most queries start.'],
            ] as [$name, $what])
                <div class="border-b border-gray-200 pb-5 last:border-0 dark:border-gray-800">
                    <dt class="font-semibold text-gray-900 dark:text-white">{{ $name }}</dt>
                    <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $what }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-6 text-sm text-gray-600 dark:text-gray-400">
            Check the upload file against the template you download from the portal before sending it. Templates change.
        </p>
    </section>

    @if ($live->eInvoicing())
        <section class="border-t border-gray-200 dark:border-gray-800" aria-label="Electronic invoicing">
            <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Electronic invoicing</h2>
                <p class="mt-3 max-w-2xl text-gray-700 dark:text-gray-300">
                    Invoices can be sent to the Nigeria Revenue Service from MyBooks, using your own NRS keys.
                </p>
            </div>
        </section>
    @endif

    <x-slot:related>
        <li><a href="{{ route('guides.show', 'vat-returns-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">How to file VAT returns in Nigeria</a> — the rules, the deadline and the penalties</li>
        <li><a href="{{ route('guides.show', 'e-invoicing-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">Nigeria's e-invoicing rules</a> — when it reaches a business your size</li>
        <li><a href="{{ route('guides.show', 'withholding-tax-nigeria') }}" class="font-medium text-brand-600 underline dark:text-brand-300">Withholding tax in Nigeria</a> — the other deduction due on the 21st</li>
    </x-slot:related>
</x-public.feature-page>
