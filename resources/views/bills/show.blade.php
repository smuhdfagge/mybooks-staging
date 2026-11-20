{{--
    The bill page. The bill itself, exactly as it will be filed and passed
    round for approval, sits on the left; what the business needs to act on
    (balance, payments, supplier credits, activity) sits on the right.
    Print prints just the bill from this page; nothing opens in a new tab.
--}}
<x-app-layout>
    @php
        $state = $document['state'];
        $currency = $document['currency'];
        $money = fn ($value) => \App\Support\Money::format($value, $currency);
        $open = ! in_array($state['key'], ['draft', 'cancelled', 'paid'], true);
        $total = (float) $bill->total;
        $settled = max(0, min($total, $total - (float) $bill->balance_due));
        $percent = $total > 0 ? (int) floor($settled / $total * 100) : 0;
        $badge = ['open' => 'unpaid', 'partial' => 'partial'][$state['key']] ?? $state['key'];
        $canPay = $open && (float) $bill->balance_due > 0 && auth()->user()->can('create payments-made');
        $printIcon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>';
    @endphp

    <x-slot name="header">
        <x-table.page-header :title="'Bill '.$bill->bill_number">
            <x-slot name="badge"><x-status-badge :status="$badge" :label="$state['label']" /></x-slot>
            <x-slot name="description">
                @if ($bill->vendor)
                    <a href="{{ route('vendors.show', $bill->vendor) }}" class="link underline">{{ $bill->vendor->name }}</a> ·
                @endif
                Billed {{ $bill->bill_date->format('j M Y') }}
                @if ($bill->due_date)
                    · Due {{ $bill->due_date->format('j M Y') }}@if ($state['daysLate'] > 0), <span class="tbl-late">{{ $state['daysLate'] }} {{ \Illuminate\Support\Str::plural('day', $state['daysLate']) }} late</span>@endif
                @endif
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('bills.pdf', $bill)">Download PDF</x-table.menu-item>
                @can('create bills')
                    <x-table.menu-item :href="route('vendor-credits.create', ['bill_id' => $bill->id])">Return goods or record a credit note</x-table.menu-item>
                @endcan
            </x-slot>
            <x-slot name="actions">
                <button type="button" class="btn-secondary gap-2" data-print data-testid="print-bill">{!! $printIcon !!}<span>Print</span></button>
                @can('edit bills')
                    <a href="{{ route('bills.edit', $bill) }}" class="btn-secondary">Edit</a>
                @endcan
                @if ($canPay)
                    <a href="{{ route('payments-made.create', ['bill_id' => $bill->id]) }}" class="btn-primary">Record payment</a>
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem] print:block">
        {{-- The bill, on its sheet of paper --}}
        <div class="min-w-0 space-y-6 print:space-y-0">
            <section aria-label="The bill as it will be filed" class="rounded-xl bg-gray-100 px-0 py-3 sm:p-6 dark:bg-gray-950/60 print:rounded-none print:bg-white print:p-0">
                <div class="mx-auto max-w-[820px] overflow-hidden bg-white shadow-[0_1px_2px_rgba(16,42,67,0.08),0_16px_36px_-16px_rgba(16,42,67,0.35)] ring-1 ring-gray-900/5 sm:rounded-md print:max-w-none print:rounded-none print:shadow-none print:ring-0">
                    @include('bills.document', $document)
                </div>
                <p class="mt-3 px-3 text-center text-xs text-gray-600 dark:text-gray-400 print:hidden">
                    Print this, sign it, and file it with the supplier's original.
                    @can('view settings')<a href="{{ route('settings.invoice-templates.index') }}" class="link underline">Change its look</a>@endcan
                    @if (! $document['logo'] && $document['s']['show_logo'])
                        @can('edit settings')
                            <span class="mt-1 block" data-testid="add-logo-hint">Your logo is not on it yet. <a href="{{ route('settings.company') }}" class="link underline">Add your logo</a> and it will appear on every document.</span>
                        @endcan
                    @endif
                </p>
            </section>

            <div class="space-y-6 print:hidden">
                @if ($bill->journal)
                    <details class="group rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-5 py-4 focus-ring [&::-webkit-details-marker]:hidden">
                            <span>
                                <span class="font-medium text-gray-900 dark:text-white">Accounting entries</span>
                                <span class="block text-sm text-gray-600 dark:text-gray-400">How this bill was posted to the ledger, journal {{ $bill->journal->journal_number }}</span>
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
                                @foreach ($bill->journal->entries as $entry)
                                    <tr>
                                        <td><span class="mr-2 text-xs text-gray-500 dark:text-gray-400">{{ $entry->account->account_code }}</span>{{ $entry->account->name }}</td>
                                        <td class="num {{ \App\Support\Figure::tone($entry->debit) }}">@fig($entry->debit, false)</td>
                                        <td class="num {{ \App\Support\Figure::tone($entry->credit) }}">@fig($entry->credit, false)</td>
                                    </tr>
                                @endforeach
                                <x-slot name="foot">
                                    <tr>
                                        <td>Total</td>
                                        <td class="num">@fig($bill->journal->total_debit, false)</td>
                                        <td class="num">@fig($bill->journal->total_credit, false)</td>
                                    </tr>
                                </x-slot>
                            </x-table>
                            <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">
                                Posted {{ $bill->journal->posted_at?->format('j M Y, H:i') ?? 'not yet' }}.
                                <a href="{{ route('journals.show', $bill->journal) }}" class="link">Open the journal</a>
                            </p>
                        </div>
                    </details>
                @endif
            </div>
        </div>

        {{-- What to act on --}}
        <aside class="space-y-4 lg:sticky lg:top-20 print:hidden" aria-label="Payment and history">
            {{-- Balance --}}
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="bill-balance">
                @if ($state['key'] === 'paid')
                    <p class="text-sm text-gray-600 dark:text-gray-400">Paid in full</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $money($total) }}</p>
                @elseif ($state['key'] === 'draft')
                    <p class="text-sm text-gray-600 dark:text-gray-400">Draft total</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $money($total) }}</p>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Still a draft. Nothing is owed and nothing is posted until you approve it.</p>
                @elseif ($state['key'] === 'cancelled')
                    <p class="text-sm text-gray-600 dark:text-gray-400">Cancelled</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-500 line-through dark:text-gray-400">{{ $money($total) }}</p>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">Balance payable</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums {{ $state['daysLate'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-900 dark:text-white' }}">{{ $money($bill->balance_due) }}</p>
                @endif

                @if (in_array($state['key'], ['open', 'partial', 'overdue', 'paid'], true) && $total > 0)
                    <div class="mt-4" role="img" aria-label="{{ $percent }}% paid">
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full {{ $state['key'] === 'paid' ? 'bg-green-600 dark:bg-green-500' : 'bg-brand-600 dark:bg-brand-400' }}" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $money($settled) }} of {{ $money($total) }} paid</p>
                    </div>
                @endif

                @if ($open && $bill->due_date)
                    <p class="mt-3 text-sm {{ $state['daysLate'] > 0 ? 'font-medium text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300' }}">
                        @if ($state['daysLate'] > 0)
                            Was due {{ $bill->due_date->format('j M Y') }}, {{ $state['daysLate'] }} {{ \Illuminate\Support\Str::plural('day', $state['daysLate']) }} ago
                        @elseif ($bill->due_date->isToday())
                            Due today
                        @else
                            Due {{ $bill->due_date->format('j M Y') }}, in {{ (int) now()->startOfDay()->diffInDays($bill->due_date) }} {{ \Illuminate\Support\Str::plural('day', (int) now()->startOfDay()->diffInDays($bill->due_date)) }}
                        @endif
                    </p>
                @endif

                @if ($canPay)
                    <a href="{{ route('payments-made.create', ['bill_id' => $bill->id]) }}" class="btn-primary mt-4 w-full justify-center">Record payment</a>
                @endif
            </section>

            {{-- Payments --}}
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                <h2 class="section-title">Payments</h2>
                @forelse ($bill->payments as $payment)
                    <div class="mt-3 flex items-start justify-between gap-3 border-t border-gray-100 pt-3 text-sm first-of-type:border-0 first-of-type:pt-0 dark:border-gray-700">
                        <div class="min-w-0">
                            <p class="text-gray-900 dark:text-gray-100">{{ $payment->payment_date?->format('j M Y') ?? '—' }}</p>
                            <p class="truncate text-gray-600 dark:text-gray-400">
                                {{ ucfirst(str_replace('_', ' ', (string) $payment->payment_method)) }}@if ($payment->reference), {{ $payment->reference }}@endif
                            </p>
                            @if ((float) $payment->wht_amount > 0)
                                <p class="text-gray-600 dark:text-gray-400">WHT withheld {{ $money($payment->wht_amount) }}</p>
                            @endif
                        </div>
                        <p class="shrink-0 font-medium tabular-nums text-green-700 dark:text-green-400">{{ $money($payment->amount) }}</p>
                    </div>
                @empty
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No payments yet.</p>
                @endforelse
            </section>

            {{-- Supplier credits --}}
            @unless (in_array($bill->status, ['draft', 'cancelled'], true))
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h2 class="section-title">Supplier credits</h2>
                    @forelse ($bill->vendorCreditApplications as $application)
                        <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                            <p class="text-gray-700 dark:text-gray-300">
                                Credit applied {{ $application->applied_date->format('j M Y') }}, from
                                <a href="{{ route('vendor-credits.show', $application->vendorCredit) }}" class="link">{{ $application->vendorCredit->vendor_credit_number }}</a>
                            </p>
                            <p class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">-{{ $money($application->amount) }}</p>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No supplier credit has been used against this bill.</p>
                    @endforelse
                    @can('create bills')
                        <a href="{{ route('vendor-credits.create', ['bill_id' => $bill->id]) }}" class="mt-3 block text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Return goods or record a credit note</a>
                    @endcan
                </section>
            @endunless

            {{-- Activity --}}
            @php
                $events = [['date' => $bill->bill_date ?? $bill->created_at, 'recorded' => $bill->created_at, 'text' => 'Bill dated', 'who' => $bill->createdBy?->name]];
                if ($bill->updated_at->gt($bill->created_at->copy()->addMinute())) {
                    $events[] = ['date' => $bill->updated_at, 'text' => 'Last changed'];
                }
                foreach ($bill->payments as $payment) {
                    $events[] = ['date' => $payment->payment_date ?? $payment->created_at, 'recorded' => $payment->created_at, 'text' => 'Payment of '.$money($payment->amount), 'who' => $payment->createdBy?->name];
                }
                if ($bill->inventory_updated_at) {
                    $events[] = ['date' => $bill->inventory_updated_at, 'text' => 'Stock taken in'];
                }
            @endphp
            <x-activity-timeline :events="$events" />
        </aside>
    </div>

    @include('partials.invoice-print-page')
</x-app-layout>
