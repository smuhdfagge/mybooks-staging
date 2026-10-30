<x-app-layout>
    <x-slot name="header">
        @php $canVoid = in_array($credit->status, ['draft', 'open']) && $credit->applications->isEmpty() && $credit->refunds->isEmpty(); @endphp
        <x-table.page-header :title="'Supplier credit '.$credit->vendor_credit_number">
            <x-slot name="badge">@include('vendor-credits._status', ['status' => $credit->status])</x-slot>
            <x-slot name="description">
                <a href="{{ route('vendors.show', $credit->vendor) }}" class="link">{{ $credit->vendor->name }}</a>
                · {{ $credit->credit_date->format('j M Y') }}
                @if ($credit->bill) · for bill <a href="{{ route('bills.show', $credit->bill) }}" class="link">{{ $credit->bill->bill_number }}</a>@endif
                @if ($credit->vendor_reference) · supplier's ref {{ $credit->vendor_reference }}@endif
            </x-slot>
            @if ($canVoid || in_array($credit->status, ['draft', 'void'], true))
                <x-slot name="more">
                    @can('edit bills')
                        @if ($canVoid)
                            <x-table.menu-item danger :post="route('vendor-credits.void', $credit)" confirm="Void this supplier credit? Its journal is reversed and any returned goods go back into stock.">Void</x-table.menu-item>
                        @endif
                    @endcan
                    @can('delete bills')
                        @if (in_array($credit->status, ['draft', 'void'], true))
                            <x-table.menu-item danger :post="route('vendor-credits.destroy', $credit)" method="DELETE" confirm="Delete this supplier credit?">Delete</x-table.menu-item>
                        @endif
                    @endcan
                </x-slot>
            @endif
            <x-slot name="actions">
                @can('edit bills')
                    @if ($credit->status === 'draft')
                        <form method="POST" action="{{ route('vendor-credits.open', $credit) }}">
                            @csrf
                            <button type="submit" class="btn-primary">Post it</button>
                        </form>
                    @endif
                @endcan
            </x-slot>
        </x-table.page-header>
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
                                    <x-field name="bill_id" label="Bill" type="select" x-model="bill" x-on:change="amount = $math.min(bills[bill] || 0, {{ (float) $credit->balance }}).toFixed(2)" required>
                                        <option value="">Choose a bill</option>
                                        @foreach($openBills as $openBill)
                                            <option value="{{ $openBill->id }}">{{ $openBill->bill_number }} · due {{ $openBill->due_date->format('j M Y') }} · owes {{ \App\Support\Money::format($openBill->balance_due) }}</option>
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
                <x-table caption="Lines">
<x-slot name="head">
                                <x-table.th>Description</x-table.th>
                                <x-table.th num>Qty</x-table.th>
                                <x-table.th num>Price</x-table.th>
                                <x-table.th num>VAT</x-table.th>
                                <x-table.th num>Total</x-table.th>
                                <x-table.th num class="hidden sm:table-cell">Cost of goods returned</x-table.th>
</x-slot>

                            @foreach($credit->items as $line)
                                <tr>
                                    <td>
                                        {{ $line->description }}
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $line->item?->name ?? ($line->account ? $line->account->account_code.' '.$line->account->name : 'Miscellaneous expense') }}</span>
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 4), '0'), '.') }}</td>
                                    <td class="num">@money($line->unit_price)</td>
                                    <td class="num">@money($line->tax_amount)</td>
                                    <td class="num">@money($line->total)</td>
                                    <td class="num hidden sm:table-cell">{{ $line->unit_cost !== null ? \App\Support\Money::format((float) $line->unit_cost * (float) $line->quantity) : '—' }}</td>
                                </tr>
                            @endforeach
</x-table>
            </x-card>

            @if($credit->applications->isNotEmpty() || $credit->refunds->isNotEmpty())
                <x-card title="Where the credit went">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700 p-6 pt-3 text-sm text-gray-900 dark:text-gray-100">
                        @foreach($credit->applications as $application)
                            <li class="py-2 flex justify-between gap-3">
                                <span>{{ $application->applied_date->format('j M Y') }} · used against bill <a href="{{ route('bills.show', $application->bill) }}" class="text-brand-600 dark:text-brand-300">{{ $application->bill->bill_number }}</a></span>
                                <span class="font-medium">@money($application->amount)</span>
                            </li>
                        @endforeach
                        @foreach($credit->refunds as $refund)
                            <li class="py-2 flex justify-between gap-3">
                                <span>{{ $refund->refund_date->format('j M Y') }} · refunded by the supplier{{ $refund->bank ? ' into '.$refund->bank->name : '' }}{{ $refund->reference ? ' ('.$refund->reference.')' : '' }}</span>
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
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $journal->journal_number }} · {{ $journal->journal_date->format('j M Y') }} · {{ $journal->description }}</p>
                                <x-table caption="Ledger postings">
<x-slot name="head">
</x-slot>
                                    @foreach($journal->entries as $entry)
                                        <tr>
                                            <td>{{ $entry->account->account_code }} {{ $entry->account->name }}</td>
                                            <td class="num">{{ (float) $entry->debit ? \App\Support\Money::format($entry->debit) : '' }}</td>
                                            <td class="num">{{ (float) $entry->credit ? \App\Support\Money::format($entry->credit) : '' }}</td>
                                        </tr>
                                    @endforeach
</x-table>
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
