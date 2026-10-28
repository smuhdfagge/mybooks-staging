{{-- Bank reconcile (tables plan T7): tick the lines on the bank statement, enter its closing balance, and check they agree. --}}
@php
    $cur = \App\Support\Money::symbol($bank->currency ?: null);
    $amountsIn = trim($cur);
    $typeLabels = \App\Models\BankTransaction::getTypes();
    $filtered = filled($fromDate) || filled($toDate);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$bank->name.': reconcile'" :description="'Tick the lines that are on your bank statement and enter its closing balance; they must agree. Amounts in '.$amountsIn.'.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('banks.show', $bank)">Bank account</x-table.menu-item>
                <x-table.menu-item :href="route('banks.transactions', $bank)">Money in and out</x-table.menu-item>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-6">
        {{-- The message itself is shown by the layout (U9); this adds the figure. --}}
        @if (session('error') && session('reconciliation_difference'))
            <p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100">
                Difference: {{ $cur }}{{ number_format(session('reconciliation_difference'), 2) }}
            </p>
        @endif

        <x-report.stats :cols="4">
            <x-report.stat label="Reconciled balance" :value="\App\Support\Figure::show($summary['reconciled_balance'], false)" hint="Opening balance and ticked lines" />
            <x-report.stat label="Book balance" :value="\App\Support\Figure::show($summary['book_balance'], false)" />
            <x-report.stat label="Difference" :value="\App\Support\Figure::show($summary['difference'], false)" :tone="abs($summary['difference']) < 0.005 ? 'good' : 'bad'" />
            <x-report.stat label="Lines to reconcile" :value="$summary['unreconciled_count']" :hint="$summary['last_reconciled_date'] ? 'Last done '.$summary['last_reconciled_date']->format('j M Y') : 'Never done'" />
        </x-report.stats>

        @if (! empty($feed))
            @include('banks._feed-panel')
        @endif

        <x-report.filters :action="route('banks.reconcile', $bank)" button="Show">
            <x-report.date name="from_date" label="From" :value="$fromDate" />
            <x-report.date name="to_date" label="To" :value="$toDate" />
        </x-report.filters>

        @if ($unreconciledTransactions->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered)
                    <x-table.empty filtered title="Nothing to reconcile in these dates" :clear="route('banks.reconcile', $bank)" />
                @elseif (abs($summary['difference']) >= 0.005)
                    {{-- Audit MB-08: never "All caught up" while a difference remains; say why there is nothing to tick. --}}
                    @if (! $bank->chart_of_account_id)
                        <x-table.empty title="No bank lines to reconcile" text="This bank isn't linked to a ledger account, so the books have no lines for it. Link one under Edit, and its receipts, payments and journals appear here to tick." />
                    @elseif (! $fromLedger)
                        <x-table.empty title="No bank lines to reconcile" text="Lines come from the bank feed. Match or post the feed's lines and they appear here as reconciled." />
                    @else
                        <x-table.empty title="Nothing left to tick" text="Every line in the books is ticked, but the difference isn't zero: a line ticked earlier may since have been changed or deleted in the books." />
                    @endif
                @else
                    <x-table.empty title="All caught up" text="Every bank line has been reconciled." />
                @endif
            </div>
        @else
            <form method="POST" action="{{ route('banks.reconcile.process', $bank) }}" x-data="reconciliationForm()" class="space-y-3">
                @csrf
                <div class="flex flex-wrap items-end gap-3">
                    <label class="flex flex-col gap-1 text-[13px] font-medium text-gray-700 dark:text-gray-300">
                        Statement closing balance
                        <input type="number" step="0.01" name="statement_balance" required x-model="statementBalance" placeholder="From your bank statement"
                            class="h-9 w-56 rounded-md border-gray-300 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    </label>
                    <label class="flex flex-col gap-1 text-[13px] font-medium text-gray-700 dark:text-gray-300">
                        Statement date
                        <input type="date" name="statement_date" required value="{{ now()->format('Y-m-d') }}"
                            class="h-9 rounded-md border-gray-300 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    </label>
                    <span class="flex-1"></span>
                    <p class="text-sm text-gray-700 dark:text-gray-300" aria-live="polite">
                        <span x-text="selectedCount"></span> ticked ·
                        in <span class="font-medium tabular-nums" x-text="formatMoney(selectedDeposits, '')"></span> ·
                        out <span class="font-medium tabular-nums" x-text="formatMoney(selectedWithdrawals, '')"></span>
                    </p>
                    <button type="submit" class="btn-new" x-bind:disabled="selectedCount === 0" x-bind:class="selectedCount === 0 && 'opacity-50'">Reconcile ticked lines</button>
                </div>

                <x-table caption="Bank lines not reconciled yet">
                    <x-slot name="head">
                        <th scope="col" class="tbl-check">
                            <input type="checkbox" class="tbl-checkbox" aria-label="Tick every line" x-on:change="toggleAll($event.target.checked)">
                        </th>
                        <x-table.th>Date</x-table.th>
                        <x-table.th>What</x-table.th>
                        <x-table.th class="hidden md:table-cell">Reference</x-table.th>
                        <x-table.th class="hidden lg:table-cell">Payee</x-table.th>
                        <x-table.th num>Money in</x-table.th>
                        <x-table.th num>Money out</x-table.th>
                    </x-slot>
                    @foreach ($unreconciledTransactions as $txn)
                        <tr>
                            <td class="tbl-check">
                                <input type="checkbox" name="transaction_ids[]" value="{{ $txn->id }}" x-on:change="updateTotals()"
                                    data-amount="{{ $txn->amount }}" data-inflow="{{ $txn->isInflow() ? '1' : '0' }}"
                                    class="txn-checkbox tbl-checkbox" aria-label="Tick {{ $txn->reference ?: 'line' }} of {{ $txn->date->format('j M Y') }}">
                            </td>
                            <td class="">{{ $txn->date->format('j M Y') }}</td>
                            <td class="rpt-wrap max-w-[22rem]">
                                <span class="font-medium">{{ $typeLabels[$txn->type] ?? \Illuminate\Support\Str::headline((string) $txn->type) }}</span>
                                @if ($txn->description)<div class="text-xs tbl-muted">{{ $txn->description }}</div>@endif
                            </td>
                            <td class="hidden md:table-cell {{ $txn->reference ? '' : 'tbl-zero' }}">{{ $txn->reference ?: '—' }}</td>
                            <td class="hidden lg:table-cell {{ $txn->payee ? '' : 'tbl-zero' }}">{{ $txn->payee ?: '—' }}</td>
                            <td class="num {{ $txn->isInflow() ? '' : 'tbl-zero' }}">@if ($txn->isInflow())@fig($txn->amount, false)@else—@endif</td>
                            <td class="num {{ $txn->isOutflow() ? '' : 'tbl-zero' }}">@if ($txn->isOutflow())@fig($txn->amount, false)@else—@endif</td>
                        </tr>
                    @endforeach
                </x-table>
            </form>
        @endif
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function reconciliationForm() {
            return {
                selectedDeposits: 0,
                selectedWithdrawals: 0,
                selectedCount: 0,
                statementBalance: '',

                toggleAll(checked) {
                    document.querySelectorAll('.txn-checkbox').forEach(cb => {
                        cb.checked = checked;
                    });
                    this.updateTotals();
                },

                updateTotals() {
                    let deposits = 0;
                    let withdrawals = 0;
                    let count = 0;

                    document.querySelectorAll('.txn-checkbox:checked').forEach(cb => {
                        const amount = parseFloat(cb.dataset.amount);
                        const isInflow = cb.dataset.inflow === '1';
                        if (isInflow) {
                            deposits += amount;
                        } else {
                            withdrawals += amount;
                        }
                        count++;
                    });

                    this.selectedDeposits = deposits;
                    this.selectedWithdrawals = withdrawals;
                    this.selectedCount = count;
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
