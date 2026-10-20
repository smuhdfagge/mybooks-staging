{{--
    The payment made page. The payment advice itself, exactly as the
    supplier gets it, sits on the left; what the business needs (the
    advance balance, the WHT withheld, the ledger) sits on the right.
    Print prints just the advice; nothing opens in a new tab.
--}}
<x-app-layout>
    @php
        $pm = $paymentMade;
        $currency = $document['currency'];
        $money = fn ($value) => \App\Support\Money::format($value, $currency);
        $wht = (float) $pm->wht_amount;
        $printIcon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>';
    @endphp

    <x-slot name="header">
        <x-table.page-header :title="'Payment '.$pm->payment_number">
            @if ($pm->is_advance)
                <x-slot name="badge"><x-status-badge status="open" label="Advance" /></x-slot>
            @endif
            <x-slot name="description">
                @if ($pm->vendor)<a href="{{ route('vendors.show', $pm->vendor) }}" class="link underline">{{ $pm->vendor->name }}</a> · @endif
                Paid {{ $pm->payment_date?->format('j M Y') ?? '—' }} · {{ $money($pm->amount) }}
                @if ($pm->bill) · for bill <a href="{{ route('bills.show', $pm->bill) }}" class="link underline">{{ $pm->bill->bill_number }}</a>@endif
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('payments-made.pdf', $pm)">Download PDF</x-table.menu-item>
                @can('delete payments-made')
                    <x-table.menu-item danger :post="route('payments-made.destroy', $pm)" method="DELETE" confirm="Delete this payment? Its journal is reversed.">Delete payment</x-table.menu-item>
                @endcan
            </x-slot>
            <x-slot name="actions">
                <button type="button" class="btn-secondary gap-2" data-print data-testid="print-payment-advice">{!! $printIcon !!}<span>Print</span></button>
                @can('edit payments-made')
                    <a href="{{ route('payments-made.edit', $pm) }}" class="btn-secondary">Edit</a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem] print:block">
        {{-- The advice, on its sheet of paper --}}
        <div class="min-w-0 space-y-6 print:space-y-0">
            <section aria-label="The payment advice as your supplier gets it" class="rounded-xl bg-gray-100 px-0 py-3 sm:p-6 dark:bg-gray-950/60 print:rounded-none print:bg-white print:p-0">
                <div class="mx-auto max-w-[820px] overflow-hidden bg-white shadow-[0_1px_2px_rgba(16,42,67,0.08),0_16px_36px_-16px_rgba(16,42,67,0.35)] ring-1 ring-gray-900/5 sm:rounded-md print:max-w-none print:rounded-none print:shadow-none print:ring-0">
                    @include('payments.document', $document)
                </div>
                <p class="mt-3 px-3 text-center text-xs text-gray-600 dark:text-gray-400 print:hidden">
                    Send this to the supplier so they can match the payment{{ $wht > 0 ? ' and claim back the withholding tax' : '' }}.
                    @can('view settings')<a href="{{ route('settings.invoice-templates.index') }}" class="link underline">Change its look</a>@endcan
                    @if (! $document['logo'] && $document['s']['show_logo'])
                        @can('edit settings')
                            <span class="mt-1 block" data-testid="add-logo-hint">Your logo is not on it yet. <a href="{{ route('settings.company') }}" class="link underline">Add your logo</a> and it will appear on every document.</span>
                        @endcan
                    @endif
                </p>
            </section>

            <div class="space-y-6 print:hidden">
                @if ($pm->journal)
                    <details class="group rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-5 py-4 focus-ring [&::-webkit-details-marker]:hidden">
                            <span>
                                <span class="font-medium text-gray-900 dark:text-white">Accounting entries</span>
                                <span class="block text-sm text-gray-600 dark:text-gray-400">How this payment was posted to the ledger, journal {{ $pm->journal->journal_number }}</span>
                            </span>
                            <svg class="h-5 w-5 shrink-0 text-gray-500 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                        </summary>
                        <div class="border-t border-gray-100 px-5 pb-5 pt-4 dark:border-gray-700">
                            <x-table caption="Ledger postings">
                                <x-slot name="head">
                                    <x-table.th>Account</x-table.th>
                                    <x-table.th num>Debit</x-table.th>
                                    <x-table.th num>Credit</x-table.th>
                                </x-slot>
                                @foreach ($pm->journal->entries as $entry)
                                    <tr>
                                        <td><span class="mr-2 text-xs text-gray-500 dark:text-gray-400">{{ $entry->account->account_code }}</span>{{ $entry->account->name }}</td>
                                        <td class="num {{ \App\Support\Figure::tone($entry->debit) }}">@fig($entry->debit, false)</td>
                                        <td class="num {{ \App\Support\Figure::tone($entry->credit) }}">@fig($entry->credit, false)</td>
                                    </tr>
                                @endforeach
                            </x-table>
                            <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">
                                Posted {{ $pm->journal->posted_at?->format('j M Y, H:i') ?? 'not yet' }}.
                                <a href="{{ route('journals.show', $pm->journal) }}" class="link">Open the journal</a>
                            </p>
                        </div>
                    </details>
                @endif
            </div>
        </div>

        {{-- What to act on --}}
        <aside class="space-y-4 lg:sticky lg:top-20 print:hidden" aria-label="Payment details">
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="payment-amount">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $pm->is_advance ? 'Advance paid' : 'Paid' }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $money($pm->amount) }}</p>
                @if ($wht > 0)
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        You withheld {{ $money($wht) }} as withholding tax, so {{ $money($pm->settledAmount()) }} came off the bill.
                    </p>
                @endif
                @if ($pm->is_advance)
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Still available</dt><dd class="font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $money($pm->unused_amount) }}</dd></div>
                    </dl>
                @endif
            </section>

            {{-- Withholding tax we kept back and have to remit --}}
            @if ($wht > 0)
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="wht-panel">
                    <h3 class="font-medium text-gray-900 dark:text-white">Withholding tax</h3>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-gray-600 dark:text-gray-400">Withheld</dt><dd class="font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $money($wht) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-600 dark:text-gray-400">Rate</dt><dd class="text-gray-900 dark:text-gray-100">{{ \App\Support\Figure::rate($pm->wht_rate) }}</dd></div>
                        @if ($pm->whtCategory)
                            <div class="flex justify-between gap-3"><dt class="text-gray-600 dark:text-gray-400">On</dt><dd class="text-right text-gray-900 dark:text-gray-100">{{ $pm->whtCategory->name }}</dd></div>
                        @endif
                        @if ($pm->wht_authority)
                            <div class="flex justify-between gap-3"><dt class="text-gray-600 dark:text-gray-400">Remit to</dt><dd class="text-right text-gray-900 dark:text-gray-100">{{ \App\Services\Accounting\WithholdingTax::authorityLabel((string) $pm->wht_authority, $pm->wht_state) }}</dd></div>
                        @endif
                    </dl>
                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        You owe this to the tax authority, not to the supplier. Send them the advice so they can claim it back.
                    </p>
                </section>
            @endif

            {{-- Where an advance went --}}
            @if ($pm->is_advance && $pm->advanceApplications->isNotEmpty())
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h3 class="font-medium text-gray-900 dark:text-white">Where it went</h3>
                    @foreach ($pm->advanceApplications as $application)
                        <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                            <p class="text-gray-700 dark:text-gray-300">
                                {{ $application->applied_date?->format('j M Y') }}, against
                                <a href="{{ route('bills.show', $application->bill) }}" class="link">{{ $application->bill->bill_number }}</a>
                            </p>
                            <p class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $money($application->amount) }}</p>
                        </div>
                    @endforeach
                </section>
            @endif

            {{-- Supplier --}}
            @if ($pm->vendor)
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h3 class="font-medium text-gray-900 dark:text-white">Supplier</h3>
                    <p class="mt-2 text-sm"><a href="{{ route('vendors.show', $pm->vendor) }}" class="link">{{ $pm->vendor->name }}</a></p>
                    @if ($pm->vendor->email)
                        <p class="truncate text-sm"><a href="mailto:{{ $pm->vendor->email }}" class="link">{{ $pm->vendor->email }}</a></p>
                    @endif
                    @if ($pm->vendor->phone)
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $pm->vendor->phone }}</p>
                    @endif
                </section>
            @endif
        </aside>
    </div>

    @include('partials.invoice-print-page')
</x-app-layout>
