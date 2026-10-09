<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-3">
                    Supplier credit {{ $credit->vendor_credit_number }}
                    @include('vendor-credits._status', ['status' => $credit->status])
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('vendors.show', $credit->vendor) }}" class="text-brand-600 dark:text-brand-300">{{ $credit->vendor->name }}</a>
                    · {{ $credit->credit_date->format('d M Y') }}
                    @if($credit->bill) · for bill <a href="{{ route('bills.show', $credit->bill) }}" class="text-brand-600 dark:text-brand-300">{{ $credit->bill->bill_number }}</a>@endif
                    @if($credit->vendor_reference) · supplier's ref {{ $credit->vendor_reference }}@endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('edit bills')
                    @if($credit->status === 'draft')
                        <form method="POST" action="{{ route('vendor-credits.open', $credit) }}">@csrf
                            <button class="btn-primary">Post it</button>
                        </form>
                    @endif
                    @if(in_array($credit->status, ['draft', 'open']) && $credit->applications->isEmpty() && $credit->refunds->isEmpty())
                        <form method="POST" action="{{ route('vendor-credits.void', $credit) }}" data-confirm="Void this supplier credit? Its journal is reversed and any returned goods go back into stock.">@csrf
                            <button class="inline-flex items-center px-4 py-2 rounded-md bg-red-600 text-white text-xs font-semibold uppercase tracking-widest hover:bg-red-700">Void</button>
                        </form>
                    @endif
                @endcan
                @can('delete bills')
                    @if(in_array($credit->status, ['draft', 'void']))
                        <form method="POST" action="{{ route('vendor-credits.destroy', $credit) }}" data-confirm="Delete this supplier credit?">@csrf @method('DELETE')
                            <button class="inline-flex items-center px-4 py-2 rounded-md border border-red-300 text-red-700 dark:text-red-300 text-xs font-semibold uppercase tracking-widest">Delete</button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('vendor-credits.index') }}" class="inline-flex items-center px-4 py-2 rounded-md bg-gray-600 text-white text-xs font-semibold uppercase tracking-widest">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-error-summary />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Credit total</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($credit->total)</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">incl. VAT @money($credit->tax_amount)</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Used or refunded</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($credit->usedAmount())</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Left to use</p>
                    <p class="text-2xl font-semibold text-brand-600 dark:text-brand-300">@money($credit->balance)</p>
                </x-card>
            </div>

            @if($credit->status === 'draft')
                <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 p-4 text-sm text-yellow-800 dark:text-yellow-200">
                    This is a draft: nothing has been posted and no goods have left stock. Press "Post it" when it is right.
                </div>
            @endif

            @if($credit->isOpen() && (float) $credit->balance > 0)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @can('edit bills')
                    <x-card title="Use against a bill" class="pb-6">
                        <div class="px-6 pt-2">
                            @if($openBills->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $credit->vendor->name }} has no unpaid bills. You can record a refund instead.</p>
                            @else
                                <form method="POST" action="{{ route('vendor-credits.apply', $credit) }}" class="space-y-3"
                                      x-data="{ bills: @js($openBills->mapWithKeys(fn ($b) => [$b->id => (float) $b->balance_due])), bill: '', amount: '' }">
                                    @csrf
                                    <x-field name="bill_id" label="Bill" type="select" x-model="bill" x-on:change="amount = Math.min(bills[bill] || 0, {{ (float) $credit->balance }}).toFixed(2)" required>
                                        <option value="">Choose a bill</option>
                                        @foreach($openBills as $openBill)
                                            <option value="{{ $openBill->id }}">{{ $openBill->bill_number }} · due {{ $openBill->due_date->format('d M Y') }} · owes {{ \App\Support\Money::format($openBill->balance_due) }}</option>
                                        @endforeach
                                    </x-field>
                                    <x-field name="amount" label="Amount to use" type="number" step="0.01" min="0.01" x-model="amount" required />
                                    <button class="btn-primary">Use credit</button>
                                </form>
                            @endif
                        </div>
                    </x-card>
                    <x-card title="Refund from the supplier" class="pb-6">
                        <form method="POST" action="{{ route('vendor-credits.refund', $credit) }}" class="px-6 pt-2 space-y-3">
                            @csrf
                            <p class="text-sm text-gray-500 dark:text-gray-400">When the supplier pays the credit back to you.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div><x-field name="refund_date" label="Date received" type="date" :value="old('refund_date', now()->toDateString())" required /></div>
                                <div><x-field name="amount" id="refund_amount" label="Amount" type="number" step="0.01" min="0.01" :value="old('amount', number_format((float) $credit->balance, 2, '.', ''))" required /></div>
                                <div>
                                    <x-field name="payment_method" label="Received by" type="select" required>
                                        <option value="bank_transfer">Bank transfer</option>
                                        <option value="cash">Cash</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="mobile_money">Mobile money</option>
                                        <option value="other">Other</option>
                                    </x-field>
                                </div>
                                <div>
                                    <x-field name="bank_id" label="Into bank account" type="select">
                                        <option value="">—</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                        @endforeach
                                    </x-field>
                                </div>
                            </div>
                            <x-field name="reference" label="Reference" :value="old('reference')" />
                            <button class="btn-primary">Record refund</button>
                        </form>
                    </x-card>
                    @endcan
                </div>
            @endif

            <x-card title="Lines">
                <div class="overflow-x-auto p-6 pt-3">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-3">Description</th>
                                <th class="py-2 pr-3 text-right">Qty</th>
                                <th class="py-2 pr-3 text-right">Price</th>
                                <th class="py-2 pr-3 text-right">VAT</th>
                                <th class="py-2 pr-3 text-right">Total</th>
                                <th class="py-2 text-right hidden sm:table-cell">Cost of goods returned</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                            @foreach($credit->items as $line)
                                <tr>
                                    <td class="py-2 pr-3">
                                        {{ $line->description }}
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $line->item?->name ?? ($line->account ? $line->account->account_code.' '.$line->account->name : 'Miscellaneous expense') }}</span>
                                    </td>
                                    <td class="py-2 pr-3 text-right">{{ rtrim(rtrim(number_format((float) $line->quantity, 4), '0'), '.') }}</td>
                                    <td class="py-2 pr-3 text-right">@money($line->unit_price)</td>
                                    <td class="py-2 pr-3 text-right">@money($line->tax_amount)</td>
                                    <td class="py-2 pr-3 text-right">@money($line->total)</td>
                                    <td class="py-2 text-right hidden sm:table-cell">{{ $line->unit_cost !== null ? \App\Support\Money::format((float) $line->unit_cost * (float) $line->quantity) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if($credit->applications->isNotEmpty() || $credit->refunds->isNotEmpty())
                <x-card title="Where the credit went">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700 p-6 pt-3 text-sm text-gray-900 dark:text-gray-100">
                        @foreach($credit->applications as $application)
                            <li class="py-2 flex justify-between gap-3">
                                <span>{{ $application->applied_date->format('d M Y') }} · used against bill <a href="{{ route('bills.show', $application->bill) }}" class="text-brand-600 dark:text-brand-300">{{ $application->bill->bill_number }}</a></span>
                                <span class="font-medium">@money($application->amount)</span>
                            </li>
                        @endforeach
                        @foreach($credit->refunds as $refund)
                            <li class="py-2 flex justify-between gap-3">
                                <span>{{ $refund->refund_date->format('d M Y') }} · refunded by the supplier{{ $refund->bank ? ' into '.$refund->bank->name : '' }}{{ $refund->reference ? ' ('.$refund->reference.')' : '' }}</span>
                                <span class="font-medium">@money($refund->amount)</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if($credit->journals->isNotEmpty())
                <x-card title="Ledger postings">
                    <div class="p-6 pt-3 space-y-4">
                        @foreach($credit->journals as $journal)
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $journal->journal_number }} · {{ $journal->journal_date->format('d M Y') }} · {{ $journal->description }}</p>
                                <table class="min-w-full text-sm mt-1">
                                    @foreach($journal->entries as $entry)
                                        <tr class="text-gray-900 dark:text-gray-100">
                                            <td class="py-1 pr-3">{{ $entry->account->account_code }} {{ $entry->account->name }}</td>
                                            <td class="py-1 pr-3 text-right">{{ (float) $entry->debit ? \App\Support\Money::format($entry->debit) : '' }}</td>
                                            <td class="py-1 text-right">{{ (float) $entry->credit ? \App\Support\Money::format($entry->credit) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if($credit->notes)
                <x-card title="Notes"><p class="p-6 pt-3 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $credit->notes }}</p></x-card>
            @endif
        </div>
    </div>
</x-app-layout>
