<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Bank line</h2>
            <a href="{{ route('bank-feeds.lines') }}" class="btn-secondary">Back to the list</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-card class="p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $line->date->format('j M Y') }} · {{ $line->connection?->title() }} · {{ $line->bank?->name }}</p>
                        <p class="mt-1 text-gray-900 dark:text-gray-100 break-words" data-testid="line-narration">{{ $line->narration ?: 'No description from the bank' }}</p>
                    </div>
                    <p class="text-xl font-semibold whitespace-nowrap {{ $line->isCredit() ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $line->isCredit() ? '+' : '−' }}@money($line->amount)</p>
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $line->isCredit() ? 'Money came in.' : 'Money went out.' }}
                    Status: <strong>{{ $line->statusEnum()->label() }}</strong>.
                    @if($line->matched) Linked to <strong>{{ $line->matched->payment_number ?? $line->matched->expense_number ?? $line->matched->journal_number ?? '#'.$line->matched_id }}</strong>. @endif
                </p>
            </x-card>

            @if($line->isNew())
                @can('reconcile banks')
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        This only records the line once you press a button. The date and amount are taken from the bank and can't be changed here.
                        If the date falls in a locked period, MyBooks will refuse and say why.
                    </p>

                    @if($line->isCredit())
                        {{-- Money in --}}
                        @can('create payments-received')
                            <x-card class="p-5" title="Payment from a customer">
                                <div class="px-6 pb-5 pt-3 space-y-4">
                                    <form method="GET" action="{{ route('bank-feeds.lines.show', $line) }}" class="space-y-3">
                                        <x-customer-picker :options="$customerOptions" :value="$customer?->id ?? ''" label="Who paid?" />
                                        <button type="submit" class="btn-secondary" data-testid="pick-customer">Show their open invoices</button>
                                    </form>

                                    @if($customer)
                                        <form method="POST" action="{{ route('bank-feeds.lines.payment-received', $line) }}" class="space-y-3" data-testid="payment-form">
                                            @csrf
                                            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                                            <fieldset>
                                                <legend class="form-label">Pay which invoice?</legend>
                                                <div class="space-y-2">
                                                    @foreach($invoices as $inv)
                                                        <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                                                            <input type="radio" name="invoice_id" value="{{ $inv->id }}" class="mt-1" @checked($loop->first)>
                                                            <span>{{ $inv->invoice_number }} · due {{ $inv->due_date?->format('j M Y') ?? '—' }} · owing @money($inv->balance_due)</span>
                                                        </label>
                                                    @endforeach
                                                    <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                                                        <input type="radio" name="invoice_id" value="deposit" class="mt-1" @checked($invoices->isEmpty())>
                                                        <span>None, keep it as a deposit from {{ $customer->name }}</span>
                                                    </label>
                                                </div>
                                                <p class="form-help">The payment is for @money($line->amount), dated {{ $line->date->format('j M Y') }}. It may not be more than the invoice still owes.</p>
                                            </fieldset>
                                            <button type="submit" class="btn-primary" data-testid="record-payment">Record payment received</button>
                                        </form>
                                    @endif
                                </div>
                            </x-card>
                        @endcan

                        @can('create journals')
                            <x-card class="p-5" title="Other income">
                                <form method="POST" action="{{ route('bank-feeds.lines.other-income', $line) }}" class="px-6 pb-5 pt-3 space-y-3" data-testid="income-form">
                                    @csrf
                                    <x-field name="income_account_id" label="Income account" type="select" required>
                                        <option value="">Choose...</option>
                                        @foreach($incomeAccounts as $a)<option value="{{ $a->id }}">{{ $a->account_code }} · {{ $a->name }}</option>@endforeach
                                    </x-field>
                                    <x-field name="description" label="What was it for?" maxlength="150" placeholder="e.g. Interest, refund from supplier" />
                                    <button type="submit" class="btn-primary">Record other income</button>
                                </form>
                            </x-card>
                        @endcan
                    @else
                        {{-- Money out --}}
                        @can('create expenses')
                            <x-card class="p-5" title="Expense">
                                <form method="POST" action="{{ route('bank-feeds.lines.expense', $line) }}" class="px-6 pb-5 pt-3 space-y-3" data-testid="expense-form">
                                    @csrf
                                    <x-field name="name" label="What was it for?" :value="old('name', \Illuminate\Support\Str::limit((string) $line->narration, 80, ''))" maxlength="255" required />
                                    <x-field name="expense_account_id" label="Category" type="select" required>
                                        <option value="">Choose...</option>
                                        @foreach($expenseAccounts as $a)<option value="{{ $a->id }}" @selected((int) old('expense_account_id') === $a->id)>{{ $a->account_code }} · {{ $a->name }}</option>@endforeach
                                    </x-field>
                                    <x-field name="vendor_id" label="Supplier (optional)" type="select">
                                        <option value="">None</option>
                                        @foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                                    </x-field>
                                    <p class="form-help">Saved as a draft expense. Submit it, have it approved and mark it paid as usual; only then does it reach the books.</p>
                                    <button type="submit" class="btn-primary" data-testid="record-expense">Record expense</button>
                                </form>
                            </x-card>
                        @endcan

                        @can('create journals')
                            <x-card class="p-5" title="Bank charge">
                                <form method="POST" action="{{ route('bank-feeds.lines.bank-charge', $line) }}" class="px-6 pb-5 pt-3 space-y-3" data-testid="charge-form">
                                    @csrf
                                    <x-field name="expense_account_id" label="Charges account" type="select" required>
                                        <option value="">Choose...</option>
                                        @foreach($expenseAccounts as $a)<option value="{{ $a->id }}">{{ $a->account_code }} · {{ $a->name }}</option>@endforeach
                                    </x-field>
                                    <p class="form-help">Posts at once: the charges account is debited and this bank account credited. For fees and stamp duty the bank took.</p>
                                    <button type="submit" class="btn-primary">Record bank charge</button>
                                </form>
                            </x-card>
                        @endcan
                    @endif

                    @can('create journals')
                        <x-card class="p-5" title="{{ $line->isCredit() ? 'Transfer from another account' : 'Transfer to another account' }}">
                            <form method="POST" action="{{ route('bank-feeds.lines.transfer', $line) }}" class="px-6 pb-5 pt-3 space-y-3" data-testid="transfer-form">
                                @csrf
                                <x-field name="other_bank_id" label="Other account" type="select" required>
                                    <option value="">Choose...</option>
                                    @foreach($otherBanks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                                </x-field>
                                <p class="form-help">Record the transfer on one side only. On the other account's bank line, choose this transfer as the match.</p>
                                <button type="submit" class="btn-primary">Record transfer</button>
                            </form>
                        </x-card>
                    @endcan
                @endcan
            @endif
        </div>
    </div>
</x-app-layout>
