{{--
    The credit note page. The credit note itself, exactly as the customer
    gets it, sits on the left; what the business needs to act on (credit
    left, where it went, the customer) sits on the right. Print prints just
    the credit note from this page; nothing opens in a new tab.
--}}
<x-app-layout>
    @php
        $cn = $creditNote;
        $state = $document['state'];
        $currency = $document['currency'];
        $money = fn ($value) => \App\Support\Money::format($value, $currency);
        $canVoid = in_array($cn->status, ['draft', 'open'], true) && $cn->applications->isEmpty() && $cn->refunds->isEmpty();
        $canUse = $cn->isOpen() && (float) $cn->balance > 0;
        $badge = ['open' => 'unpaid', 'closed' => 'paid', 'void' => 'cancelled'][$state['key']] ?? $state['key'];
        $printIcon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>';
    @endphp

    <x-slot name="header">
        <x-table.page-header :title="'Credit note '.$cn->credit_note_number">
            <x-slot name="badge"><x-status-badge :status="$badge" :label="$state['label']" /></x-slot>
            <x-slot name="description">
                <a href="{{ route('customers.show', $cn->customer) }}" class="link underline">{{ $cn->customer->name }}</a>
                · {{ $cn->credit_note_date->format('j M Y') }}
                @if ($cn->invoice) · for invoice <a href="{{ route('invoices.show', $cn->invoice) }}" class="link underline">{{ $cn->invoice->invoice_number }}</a>@endif
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('credit-notes.pdf', $cn)">Download PDF</x-table.menu-item>
                @can('edit invoices')
                    @if ($canVoid)
                        <x-table.menu-item danger :post="route('credit-notes.void', $cn)" :confirm="'Void this credit note?'.($cn->isOpen() ? ' Its journal is reversed'.($cn->restock ? ' and the returned goods are taken back out of stock' : '').'.' : '')">Void</x-table.menu-item>
                    @endif
                @endcan
                @can('delete invoices')
                    @if ($cn->isDraft())
                        <x-table.menu-item danger :post="route('credit-notes.destroy', $cn)" method="DELETE" confirm="Delete this draft credit note?">Delete</x-table.menu-item>
                    @endif
                @endcan
            </x-slot>
            <x-slot name="actions">
                <button type="button" class="btn-secondary gap-2" data-print data-testid="print-credit-note">{!! $printIcon !!}<span>Print</span></button>
                @can('edit invoices')
                    @if ($cn->isDraft())
                        <a href="{{ route('credit-notes.edit', $cn) }}" class="btn-secondary">Edit</a>
                        <form method="POST" action="{{ route('credit-notes.open', $cn) }}">
                            @csrf
                            <button type="submit" class="btn-primary">Post it</button>
                        </form>
                    @endif
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem] print:block">
        {{-- The credit note, on its sheet of paper --}}
        <div class="min-w-0 space-y-6 print:space-y-0">
            <div class="print:hidden"><x-error-summary /></div>

            @if ($cn->isDraft())
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200 print:hidden">
                    This is a draft: nothing has been posted{{ $cn->restock ? ' and no goods have gone back into stock' : '' }}. Press "Post it" when it is right.
                </div>
            @elseif ($cn->restock && $cn->status !== 'void')
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200 print:hidden">
                    The customer returned the goods: the stock items on this credit note went back into stock at what they cost you.@if ($warehouseName = \App\Models\Warehouse::nameIfMany($cn->warehouse_id)) They went into {{ $warehouseName }}.@endif
                </div>
            @endif

            <section aria-label="The credit note as your customer sees it" class="rounded-xl bg-gray-100 px-0 py-3 sm:p-6 dark:bg-gray-950/60 print:rounded-none print:bg-white print:p-0">
                <div class="mx-auto max-w-[820px] overflow-hidden bg-white shadow-[0_1px_2px_rgba(16,42,67,0.08),0_16px_36px_-16px_rgba(16,42,67,0.35)] ring-1 ring-gray-900/5 sm:rounded-md print:max-w-none print:rounded-none print:shadow-none print:ring-0">
                    @include('credit-notes.document', $document)
                </div>
                <p class="mt-3 px-3 text-center text-xs text-gray-600 dark:text-gray-400 print:hidden">
                    This is what your customer receives.
                    @can('view settings')<a href="{{ route('settings.invoice-templates.index') }}" class="link underline">Change its look</a>@endcan
                    @if (! $document['logo'] && $document['s']['show_logo'])
                        @can('edit settings')
                            <span class="mt-1 block" data-testid="add-logo-hint">Your logo is not on it yet. <a href="{{ route('settings.company') }}" class="link underline">Add your logo</a> and it will appear on every document.</span>
                        @endcan
                    @endif
                </p>
            </section>

            <div class="space-y-6 print:hidden">
                {{-- What the returned goods went back into stock at. This is
                     your cost, so it stays off the customer's copy. --}}
                @if ($cn->restock && $cn->status !== 'void')
                    <x-card title="Back in stock at cost">
                        <x-table caption="Returned goods and what they cost you">
                            <x-slot name="head">
                                <x-table.th>Item</x-table.th>
                                <x-table.th num>Qty</x-table.th>
                                <x-table.th num>Back in stock at cost</x-table.th>
                            </x-slot>
                            @foreach ($cn->items as $line)
                                <tr>
                                    <td>
                                        {{ $line->description }}
                                        @if ($line->item)<span class="block text-xs text-gray-500 dark:text-gray-400">{{ $line->item->name }}</span>@endif
                                    </td>
                                    <td class="num">{{ \App\Support\Figure::qty($line->quantity) }}</td>
                                    <td class="num">{{ $line->unit_cost !== null ? \App\Support\Money::format((float) $line->unit_cost * (float) $line->quantity) : '—' }}</td>
                                </tr>
                            @endforeach
                        </x-table>
                    </x-card>
                @endif

                @include('e-invoices._panel', ['document' => $cn])

                @if ($canUse)
                    @can('edit invoices')
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <x-card title="Apply to an invoice" class="pb-6" id="apply">
                                <div class="px-6 pt-2">
                                    @if ($invoices->isEmpty())
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $cn->customer->name }} has no unpaid invoices. You can refund the credit instead, or keep it for their next invoice.</p>
                                    @else
                                        <form method="POST" action="{{ route('credit-notes.apply.store', $cn) }}" class="space-y-3"
                                              x-data="{ invoices: @js($invoices->mapWithKeys(fn ($i) => [$i->id => (float) $i->balance_due])), invoice: '', amount: '' }">
                                            @csrf
                                            <x-field name="invoice_id" label="Invoice" type="select" x-model="invoice" x-on:change="amount = $math.min(invoices[invoice] || 0, {{ (float) $cn->balance }}).toFixed(2)" required>
                                                <option value="">Choose an invoice</option>
                                                @foreach ($invoices as $openInvoice)
                                                    <option value="{{ $openInvoice->id }}">{{ $openInvoice->invoice_number }} · due {{ $openInvoice->due_date?->format('j M Y') }} · owes {{ \App\Support\Money::format($openInvoice->balance_due) }}</option>
                                                @endforeach
                                            </x-field>
                                            <x-field name="amount" id="apply_amount" label="Amount to apply" type="number" step="0.01" min="0.01" x-model="amount" required />
                                            <button class="btn-primary">Apply credit</button>
                                        </form>
                                    @endif
                                </div>
                            </x-card>
                            <x-card title="Refund to the customer" class="pb-6">
                                <form method="POST" action="{{ route('credit-notes.refund', $cn) }}" class="space-y-3 px-6 pt-2">
                                    @csrf
                                    <p class="text-sm text-gray-600 dark:text-gray-400">When you pay the credit back to the customer.</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div><x-field name="refund_date" label="Date paid" type="date" :value="old('refund_date', now()->toDateString())" required /></div>
                                        <div><x-field name="amount" id="refund_amount" label="Amount" type="number" step="0.01" min="0.01" :value="old('amount', number_format((float) $cn->balance, 2, '.', ''))" required /></div>
                                        <div>
                                            <x-field name="payment_method" label="Paid by" type="select" required>
                                                <option value="bank_transfer">Bank transfer</option>
                                                <option value="cash">Cash</option>
                                                <option value="cheque">Cheque</option>
                                                <option value="mobile_money">Mobile money</option>
                                                <option value="other">Other</option>
                                            </x-field>
                                        </div>
                                        <div>
                                            <x-field name="bank_id" label="From bank account" type="select">
                                                <option value="">—</option>
                                                @foreach ($banks as $bank)
                                                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                                @endforeach
                                            </x-field>
                                        </div>
                                    </div>
                                    <x-field name="reference" label="Reference" :value="old('reference')" />
                                    <button class="btn-primary">Record refund</button>
                                </form>
                            </x-card>
                        </div>
                    @endcan
                @endif

                @if ($cn->journals->isNotEmpty())
                    <details class="group rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-5 py-4 focus-ring [&::-webkit-details-marker]:hidden">
                            <span>
                                <span class="font-medium text-gray-900 dark:text-white">Accounting entries</span>
                                <span class="block text-sm text-gray-600 dark:text-gray-400">How this credit note was posted to the ledger</span>
                            </span>
                            <svg class="h-5 w-5 shrink-0 text-gray-500 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                        </summary>
                        <div class="space-y-5 border-t border-gray-100 px-5 pb-5 pt-4 dark:border-gray-700">
                            @foreach ($cn->journals as $journal)
                                <div>
                                    <x-table caption="Ledger postings">
                                        <x-slot name="head">
                                            <x-table.th>Account</x-table.th>
                                            <x-table.th num>Debit</x-table.th>
                                            <x-table.th num>Credit</x-table.th>
                                        </x-slot>
                                        @foreach ($journal->entries as $entry)
                                            <tr>
                                                <td><span class="mr-2 text-xs text-gray-500 dark:text-gray-400">{{ $entry->account->account_code }}</span>{{ $entry->account->name }}</td>
                                                <td class="num {{ \App\Support\Figure::tone($entry->debit) }}">@fig($entry->debit, false)</td>
                                                <td class="num {{ \App\Support\Figure::tone($entry->credit) }}">@fig($entry->credit, false)</td>
                                            </tr>
                                        @endforeach
                                    </x-table>
                                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                        Posted {{ $journal->posted_at?->format('j M Y, H:i') ?? 'not yet' }}.
                                        <a href="{{ route('journals.show', $journal) }}" class="link">Open journal {{ $journal->journal_number }}</a>
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        </div>

        {{-- What to act on --}}
        <aside class="space-y-4 lg:sticky lg:top-20 print:hidden" aria-label="Credit and history">
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="credit-balance">
                @if ($state['key'] === 'void')
                    <p class="text-sm text-gray-600 dark:text-gray-400">Void</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-500 line-through dark:text-gray-400">{{ $money($cn->total) }}</p>
                @elseif ($state['key'] === 'closed')
                    <p class="text-sm text-gray-600 dark:text-gray-400">Credit used up</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $money($cn->total) }}</p>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">Credit still available</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $money($cn->balance) }}</p>
                    @if ($document['used'] > 0)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $money($document['used']) }} of {{ $money($cn->total) }} already used.</p>
                    @endif
                @endif
                @if ($canUse)
                    <a href="#apply" class="btn-primary mt-4 w-full justify-center">Apply to an invoice</a>
                @endif
            </section>

            @if ($cn->applications->isNotEmpty() || $cn->refunds->isNotEmpty())
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h3 class="font-medium text-gray-900 dark:text-white">Where the credit went</h3>
                    @foreach ($cn->applications as $application)
                        <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                            <p class="text-gray-700 dark:text-gray-300">
                                {{ $application->applied_date->format('j M Y') }}, against
                                <a href="{{ route('invoices.show', $application->invoice) }}" class="link">{{ $application->invoice->invoice_number }}</a>
                            </p>
                            <p class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $money($application->amount) }}</p>
                        </div>
                    @endforeach
                    @foreach ($cn->refunds as $refund)
                        <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                            <p class="text-gray-700 dark:text-gray-300">
                                {{ $refund->refund_date->format('j M Y') }}, refunded to the customer{{ $refund->bank ? ' from '.$refund->bank->name : '' }}{{ $refund->reference ? ' ('.$refund->reference.')' : '' }}
                            </p>
                            <p class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $money($refund->amount) }}</p>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                <h3 class="font-medium text-gray-900 dark:text-white">Customer</h3>
                <p class="mt-2 text-sm"><a href="{{ route('customers.show', $cn->customer) }}" class="link">{{ $cn->customer->name }}</a></p>
                @if ($cn->customer->email)
                    <p class="truncate text-sm"><a href="mailto:{{ $cn->customer->email }}" class="link">{{ $cn->customer->email }}</a></p>
                @endif
                @if ($cn->customer->phone)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $cn->customer->phone }}</p>
                @endif
            </section>
        </aside>
    </div>

    @include('partials.invoice-print-page')
</x-app-layout>
