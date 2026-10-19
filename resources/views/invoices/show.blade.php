{{--
    The invoice page (invoice rebuild). The invoice itself, exactly as the
    customer gets it, sits on the left; what the business needs to act on
    (balance, payments, credit, activity) sits on the right. Print prints
    just the invoice from this page; nothing opens in a new tab.
--}}
<x-app-layout>
    @php
        $state = $document['state'];
        $currency = $document['currency'];
        $money = fn ($v) => \App\Support\Money::format($v, $currency);
        $maxRefundable = $invoice->amount_paid - ($invoice->total_refunded ?? 0);
        $open = ! in_array($state['key'], ['draft', 'cancelled', 'paid'], true);
        $locked = \App\Models\EInvoiceSubmission::isLocked($invoice);
        $total = (float) $invoice->total;
        $settled = max(0, min($total, $total - (float) $invoice->balance_due));
        $percent = $total > 0 ? (int) floor($settled / $total * 100) : 0;
        $badge = ['open' => 'unpaid', 'partial' => 'partial'][$state['key']] ?? $state['key'];
        $canPay = $open && (float) $invoice->balance_due > 0 && auth()->user()->can('create payments-received');
        $creditFeature = \App\Http\Middleware\EnsureFeatureEnabled::enabled('credit_notes') && ! in_array($invoice->status, ['draft', 'cancelled'], true);
        $printIcon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>';
    @endphp

    <x-slot name="header">
        <x-table.page-header :title="'Invoice '.$invoice->invoice_number">
            <x-slot name="badge"><x-status-badge :status="$badge" :label="$state['label']" /></x-slot>
            <x-slot name="description">
                <a href="{{ route('customers.show', $invoice->customer) }}" class="link underline">{{ $invoice->customer->name }}</a>
                · Issued {{ $invoice->invoice_date->format('j M Y') }}
                · Due {{ $invoice->due_date->format('j M Y') }}@if ($state['daysLate'] > 0), <span class="tbl-late">{{ $state['daysLate'] }} {{ \Illuminate\Support\Str::plural('day', $state['daysLate']) }} late</span>@endif
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('invoices.pdf', $invoice)">Download PDF</x-table.menu-item>
                @if ($invoice->released_at)
                    <x-table.menu-item :href="route('invoices.waybill', $invoice)">Waybill</x-table.menu-item>
                @endif
                @if ($maxRefundable > 0 && ! in_array($invoice->status, ['draft', 'cancelled']))
                    <x-table.menu-item :href="route('invoices.refunds.create', $invoice)">Refund the customer</x-table.menu-item>
                @endif
                @if ($creditFeature)
                    @can('create invoices')
                        <x-table.menu-item :href="route('credit-notes.create', ['invoice_id' => $invoice->id])">Create credit note</x-table.menu-item>
                    @endcan
                @endif
            </x-slot>
            <x-slot name="actions">
                <button type="button" class="btn-secondary gap-2" data-print data-testid="print-invoice">{!! $printIcon !!}<span>Print</span></button>
                @unless ($locked)
                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn-secondary">Edit</a>
                @endunless
                @if ($invoice->status === 'draft')
                    @can('send invoices')
                        <form action="{{ route('invoices.send', $invoice) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-primary">Send</button>
                        </form>
                    @endcan
                @elseif ($canPay)
                    <button type="button" class="btn-primary" data-open-modal="record-payment">Record payment</button>
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem] print:block">
        {{-- The invoice, on its sheet of paper --}}
        <div class="min-w-0 space-y-6 print:space-y-0">
            <section aria-label="The invoice as your customer sees it" class="rounded-xl bg-gray-100 px-0 py-3 sm:p-6 dark:bg-gray-950/60 print:rounded-none print:bg-white print:p-0">
                <div class="mx-auto max-w-[820px] overflow-hidden bg-white shadow-[0_1px_2px_rgba(16,42,67,0.08),0_16px_36px_-16px_rgba(16,42,67,0.35)] ring-1 ring-gray-900/5 sm:rounded-md print:max-w-none print:rounded-none print:shadow-none print:ring-0">
                    @include('invoices.document', $document)
                </div>
                <p class="mt-3 px-3 text-center text-xs text-gray-600 dark:text-gray-400 print:hidden">
                    This is what your customer receives.
                    @can('view settings')<a href="{{ route('settings.invoice-templates.index') }}" class="link underline">Change its look</a>@endcan
                    @if (! $document['logo'] && $document['s']['show_logo'])
                        @can('edit settings')
                            <span class="mt-1 block" data-testid="add-logo-hint">Your logo is not on it yet. <a href="{{ route('settings.company') }}" class="link underline">Add your logo</a> and it will appear on every invoice.</span>
                        @endcan
                    @endif
                </p>
            </section>

            <div class="space-y-6 print:hidden">
                @if (\App\Http\Middleware\EnsureFeatureEnabled::enabled('sms_whatsapp') && $invoice->status !== 'draft' && $invoice->customer)
                    @include('invoices._messages')
                @endif

                @include('e-invoices._panel', ['document' => $invoice])

                @if ($invoice->journal)
                    <details class="group rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-5 py-4 focus-ring [&::-webkit-details-marker]:hidden">
                            <span>
                                <span class="font-medium text-gray-900 dark:text-white">Accounting entries</span>
                                <span class="block text-sm text-gray-600 dark:text-gray-400">How this invoice was posted to the ledger, journal {{ $invoice->journal->journal_number }}</span>
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
                                @foreach ($invoice->journal->entries as $entry)
                                    <tr>
                                        <td><span class="mr-2 text-xs text-gray-500 dark:text-gray-400">{{ $entry->account->account_code }}</span>{{ $entry->account->name }}</td>
                                        <td class="num {{ \App\Support\Figure::tone($entry->debit) }}">@fig($entry->debit, false)</td>
                                        <td class="num {{ \App\Support\Figure::tone($entry->credit) }}">@fig($entry->credit, false)</td>
                                    </tr>
                                @endforeach
                                <x-slot name="foot">
                                    <tr>
                                        <td>Total</td>
                                        <td class="num">@fig($invoice->journal->total_debit, false)</td>
                                        <td class="num">@fig($invoice->journal->total_credit, false)</td>
                                    </tr>
                                </x-slot>
                            </x-table>
                            <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">
                                Posted {{ $invoice->journal->posted_at?->format('j M Y, H:i') ?? 'not yet' }}.
                                <a href="{{ route('journals.show', $invoice->journal) }}" class="link">Open the journal</a>
                            </p>
                        </div>
                    </details>
                @endif
            </div>
        </div>

        {{-- What to act on --}}
        <aside class="space-y-4 lg:sticky lg:top-20 print:hidden" aria-label="Payment and history">
            {{-- Balance --}}
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="invoice-balance">
                @if ($state['key'] === 'paid')
                    <p class="text-sm text-gray-600 dark:text-gray-400">Paid in full</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $money($total) }}</p>
                @elseif ($state['key'] === 'draft')
                    <p class="text-sm text-gray-600 dark:text-gray-400">Draft total</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $money($total) }}</p>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Not sent yet. Nothing is owed until you send it.</p>
                @elseif ($state['key'] === 'cancelled')
                    <p class="text-sm text-gray-600 dark:text-gray-400">Cancelled</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-500 line-through dark:text-gray-400">{{ $money($total) }}</p>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">Balance due</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums {{ $state['daysLate'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-900 dark:text-white' }}">{{ $money($invoice->balance_due) }}</p>
                @endif

                @if (in_array($state['key'], ['open', 'partial', 'overdue', 'paid'], true) && $total > 0)
                    <div class="mt-4" role="img" aria-label="{{ $percent }}% paid">
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full {{ $state['key'] === 'paid' ? 'bg-green-600 dark:bg-green-500' : 'bg-brand-600 dark:bg-brand-400' }}" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $money($settled) }} of {{ $money($total) }} paid</p>
                    </div>
                @endif

                @if ($open)
                    <p class="mt-3 text-sm {{ $state['daysLate'] > 0 ? 'font-medium text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300' }}">
                        @if ($state['daysLate'] > 0)
                            Was due {{ $invoice->due_date->format('j M Y') }}, {{ $state['daysLate'] }} {{ \Illuminate\Support\Str::plural('day', $state['daysLate']) }} ago
                        @elseif ($invoice->due_date->isToday())
                            Due today
                        @else
                            Due {{ $invoice->due_date->format('j M Y') }}, in {{ (int) now()->startOfDay()->diffInDays($invoice->due_date) }} {{ \Illuminate\Support\Str::plural('day', (int) now()->startOfDay()->diffInDays($invoice->due_date)) }}
                        @endif
                    </p>
                @endif

                @if ($canPay)
                    <button type="button" class="btn-primary mt-4 w-full justify-center" data-open-modal="record-payment">Record payment</button>
                @endif
                @if ($open && \App\Http\Middleware\EnsureFeatureEnabled::enabled('sms_whatsapp'))
                    <a href="#text-messages" class="mt-2 block text-center text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Send a reminder</a>
                @endif
            </section>

            {{-- Payments --}}
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                <h3 class="font-medium text-gray-900 dark:text-white">Payments</h3>
                @forelse ($invoice->payments as $payment)
                    <div class="mt-3 flex items-start justify-between gap-3 border-t border-gray-100 pt-3 text-sm first-of-type:border-0 first-of-type:pt-0 dark:border-gray-700">
                        <div class="min-w-0">
                            <p class="text-gray-900 dark:text-gray-100">{{ $payment->payment_date?->format('j M Y') ?? '—' }}</p>
                            <p class="truncate text-gray-600 dark:text-gray-400">
                                {{ ucfirst(str_replace('_', ' ', (string) $payment->payment_method)) }}@if ($payment->reference), {{ $payment->reference }}@endif
                            </p>
                            @if ((float) $payment->wht_amount > 0)
                                <p class="text-gray-600 dark:text-gray-400">WHT deducted {{ $money($payment->wht_amount) }}</p>
                            @endif
                        </div>
                        <p class="shrink-0 font-medium tabular-nums text-green-700 dark:text-green-400">{{ $money($payment->amount) }}</p>
                    </div>
                @empty
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No payments yet.</p>
                @endforelse
            </section>

            {{-- Credit notes --}}
            @if ($creditFeature && ($invoice->creditNotes->isNotEmpty() || $invoice->creditNoteApplications->isNotEmpty()))
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h3 class="font-medium text-gray-900 dark:text-white">Credit notes</h3>
                    @foreach ($invoice->creditNotes as $note)
                        <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                            <div>
                                <a href="{{ route('credit-notes.show', $note) }}" class="link">{{ $note->credit_note_number }}</a>
                                <p class="text-gray-600 dark:text-gray-400">{{ $note->credit_note_date->format('j M Y') }}@if ($note->restock), goods returned @endif</p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $money($note->total) }}</p>
                                <x-status-badge :status="$note->status" :label="$note->status === 'closed' ? 'Used up' : null" />
                            </div>
                        </div>
                    @endforeach
                    @foreach ($invoice->creditNoteApplications as $application)
                        <div class="mt-3 flex items-start justify-between gap-3 border-t border-gray-100 pt-3 text-sm dark:border-gray-700">
                            <p class="text-gray-700 dark:text-gray-300">Credit applied {{ $application->applied_date->format('j M Y') }}, from <a href="{{ route('credit-notes.show', $application->creditNote) }}" class="link">{{ $application->creditNote->credit_note_number }}</a></p>
                            <p class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">-{{ $money($application->amount) }}</p>
                        </div>
                    @endforeach
                </section>
            @endif

            {{-- Refunds --}}
            @if ($invoice->refunds->isNotEmpty())
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h3 class="font-medium text-gray-900 dark:text-white">Refunds</h3>
                    @foreach ($invoice->refunds as $refund)
                        <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <a href="{{ route('invoices.refunds.show', $refund) }}" class="link">{{ $refund->refund_number }}</a>
                                <p class="text-gray-600 dark:text-gray-400">{{ $refund->refund_date->format('j M Y') }}, {{ \App\Models\InvoiceRefund::METHODS[$refund->refund_method] ?? ucfirst((string) $refund->refund_method) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium tabular-nums text-red-700 dark:text-red-300">-{{ $money($refund->amount) }}</p>
                                <x-status-badge :status="$refund->status === 'completed' ? 'paid' : ($refund->status === 'pending' ? 'unpaid' : 'cancelled')" :label="ucfirst($refund->status)" />
                            </div>
                        </div>
                    @endforeach
                    <p class="mt-3 flex justify-between border-t border-gray-100 pt-3 text-sm font-medium text-gray-900 dark:border-gray-700 dark:text-white">
                        <span>Refunded</span><span class="tabular-nums">-{{ $money($invoice->total_refunded ?? 0) }}</span>
                    </p>
                </section>
            @endif

            {{-- Activity --}}
            @php
                $events = collect([['when' => $invoice->created_at, 'text' => 'Created', 'who' => $invoice->createdBy?->name]]);
                if ($invoice->updated_at->gt($invoice->created_at->copy()->addMinute())) {
                    $events->push(['when' => $invoice->updated_at, 'text' => 'Last changed', 'who' => null]);
                }
                foreach ($invoice->payments as $p) {
                    $events->push(['when' => $p->payment_date ?? $p->created_at, 'text' => 'Payment of '.$money($p->amount), 'who' => $p->createdBy?->name]);
                }
                if ($invoice->released_at) {
                    $events->push(['when' => $invoice->released_at, 'text' => 'Goods released'.($invoice->waybill_number ? ', waybill '.$invoice->waybill_number : ''), 'who' => null]);
                }
                $events = $events->sortBy(fn ($e) => $e['when'])->values();
            @endphp
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                <h3 class="font-medium text-gray-900 dark:text-white">Activity</h3>
                <ol class="mt-3 space-y-3 border-l border-gray-200 pl-4 dark:border-gray-700">
                    @foreach ($events as $event)
                        <li class="relative text-sm">
                            <span class="absolute -left-[1.3rem] top-1.5 h-2 w-2 rounded-full bg-gray-400 ring-4 ring-white dark:bg-gray-500 dark:ring-gray-800" aria-hidden="true"></span>
                            <p class="text-gray-900 dark:text-gray-100">{{ $event['text'] }}</p>
                            <p class="text-gray-600 dark:text-gray-400">
                                <time datetime="{{ $event['when']?->toIso8601String() }}">{{ $event['when']?->format('j M Y') }}</time>@if ($event['who']), {{ $event['who'] }}@endif
                            </p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>

    {{-- Record a payment without leaving the invoice --}}
    @if ($canPay)
        <x-modal name="record-payment" title="Record payment" maxWidth="md" :show="$errors->any() && old('invoice_id') == $invoice->id">
            <form action="{{ route('payments-received.store') }}" method="POST" class="space-y-4 px-6 pb-6 pt-2">
                @csrf
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
                <input type="hidden" name="back_to_invoice" value="1">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $invoice->customer->name }} owes {{ $money($invoice->balance_due) }} on {{ $invoice->invoice_number }}.</p>
                <div>
                    <label for="pay-amount" class="form-label">Amount</label>
                    <input id="pay-amount" type="number" name="amount" value="{{ old('amount', $invoice->balance_due) }}" min="0.01" max="{{ $invoice->balance_due }}" step="0.01" required class="form-control" inputmode="decimal">
                    <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="pay-date" class="form-label">Date received</label>
                        <input id="pay-date" type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required class="form-control">
                        <x-input-error :messages="$errors->get('payment_date')" class="mt-1" />
                    </div>
                    <div>
                        <label for="pay-method" class="form-label">Method</label>
                        <select id="pay-method" name="payment_method" class="form-control">
                            @foreach (['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'credit_card' => 'Card or POS', 'check' => 'Cheque', 'other' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', 'bank_transfer') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('payment_method')" class="mt-1" />
                    </div>
                </div>
                @php $payBanks = \App\Models\Bank::where('is_active', true)->orderBy('name')->get(['id', 'name', 'bank_name']); @endphp
                @if ($payBanks->isNotEmpty())
                    <div>
                        <label for="pay-bank" class="form-label">Paid into <span class="font-normal text-gray-600 dark:text-gray-400">(optional)</span></label>
                        <select id="pay-bank" name="bank_id" class="form-control">
                            <option value="">Choose an account</option>
                            @foreach ($payBanks as $bank)
                                <option value="{{ $bank->id }}" @selected((string) old('bank_id') === (string) $bank->id)>{{ $bank->name }}@if ($bank->bank_name && $bank->bank_name !== $bank->name) ({{ $bank->bank_name }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="pay-ref" class="form-label">Reference <span class="font-normal text-gray-600 dark:text-gray-400">(optional)</span></label>
                    <input id="pay-ref" type="text" name="reference" value="{{ old('reference') }}" maxlength="100" class="form-control" placeholder="Transfer or teller number">
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Did the customer take off WHT, or pay from a deposit? <a href="{{ route('payments-received.create', ['invoice_id' => $invoice->id]) }}" class="link underline">Use the full payment form</a>.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary" data-close-modal="record-payment">Cancel</button>
                    <button type="submit" class="btn-primary">Record payment</button>
                </div>
            </form>
        </x-modal>
    @endif

    @include('partials.invoice-print-page')
</x-app-layout>
