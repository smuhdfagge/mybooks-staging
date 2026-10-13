@php $cn = $creditNote; @endphp
<x-app-layout>
    <x-slot name="header">
        @php $canVoid = in_array($cn->status, ['draft', 'open']) && $cn->applications->isEmpty() && $cn->refunds->isEmpty(); @endphp
        <x-table.page-header :title="'Credit note '.$cn->credit_note_number">
            <x-slot name="badge"><x-status-badge :status="$cn->status" :label="$cn->status === 'closed' ? 'Used up' : null" /></x-slot>
            <x-slot name="description">
                <a href="{{ route('customers.show', $cn->customer) }}" class="link">{{ $cn->customer->name }}</a>
                · {{ $cn->credit_note_date->format('j M Y') }}
                @if ($cn->invoice) · for invoice <a href="{{ route('invoices.show', $cn->invoice) }}" class="link">{{ $cn->invoice->invoice_number }}</a>@endif
                @if ($cn->reason) · {{ \App\Models\CreditNote::REASONS[$cn->reason] ?? $cn->reason }}@endif
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('credit-notes.print', $cn)" new-tab>Print</x-table.menu-item>
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

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-error-summary />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Credit total</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($cn->total)</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">incl. VAT @money($cn->tax_amount)</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Applied or refunded</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($cn->usedAmount())</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Left to use</p>
                    <p class="text-2xl font-semibold text-brand-600 dark:text-brand-300">@money($cn->isOpen() ? $cn->balance : 0)</p>
                </x-card>
            </div>

            @include('e-invoices._panel', ['document' => $cn])

            @if($cn->isDraft())
                <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 p-4 text-sm text-yellow-800 dark:text-yellow-200">
                    This is a draft: nothing has been posted{{ $cn->restock ? ' and no goods have gone back into stock' : '' }}. Press "Post it" when it is right.
                </div>
            @elseif($cn->restock && $cn->status !== 'void')
                <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4 text-sm text-green-800 dark:text-green-200">
                    The customer returned the goods: the stock items on this credit note went back into stock at what they cost you.@if($warehouseName = \App\Models\Warehouse::nameIfMany($cn->warehouse_id)) They went into {{ $warehouseName }}.@endif
                </div>
            @endif

            @if($cn->isOpen() && (float) $cn->balance > 0)
                @can('edit invoices')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <x-card title="Apply to an invoice" class="pb-6" id="apply">
                        <div class="px-6 pt-2">
                            @if($invoices->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $cn->customer->name }} has no unpaid invoices. You can refund the credit instead, or keep it for their next invoice.</p>
                            @else
                                <form method="POST" action="{{ route('credit-notes.apply.store', $cn) }}" class="space-y-3"
                                      x-data="{ invoices: @js($invoices->mapWithKeys(fn ($i) => [$i->id => (float) $i->balance_due])), invoice: '', amount: '' }">
                                    @csrf
                                    <x-field name="invoice_id" label="Invoice" type="select" x-model="invoice" x-on:change="amount = Math.min(invoices[invoice] || 0, {{ (float) $cn->balance }}).toFixed(2)" required>
                                        <option value="">Choose an invoice</option>
                                        @foreach($invoices as $openInvoice)
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
                        <form method="POST" action="{{ route('credit-notes.refund', $cn) }}" class="px-6 pt-2 space-y-3">
                            @csrf
                            <p class="text-sm text-gray-500 dark:text-gray-400">When you pay the credit back to the customer.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                </div>
                @endcan
            @endif

            <x-card title="Lines">
                <x-table caption="Lines">
<x-slot name="head">
                                <x-table.th>Description</x-table.th>
                                <x-table.th num>Qty</x-table.th>
                                <x-table.th num>Price</x-table.th>
                                <x-table.th num>VAT</x-table.th>
                                <x-table.th num>Total</x-table.th>
                                @if($cn->restock)<x-table.th num class="hidden sm:table-cell">Back in stock at cost</x-table.th>@endif
</x-slot>

                            @foreach($cn->items as $line)
                                <tr>
                                    <td>
                                        {{ $line->description }}
                                        @if($line->item)<span class="block text-xs text-gray-500 dark:text-gray-400">{{ $line->item->name }}</span>@endif
                                        @if((float) $line->tax_amount == 0 && $line->vat_treatment && $line->vat_treatment !== 'standard')
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ \App\Services\Accounting\VatTreatment::label($line->vat_treatment) }}</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                                    <td class="num">@money($line->unit_price)</td>
                                    <td class="num">@money($line->tax_amount)</td>
                                    <td class="num">@money($line->total)</td>
                                    @if($cn->restock)<td class="num hidden sm:table-cell">{{ $line->unit_cost !== null ? \App\Support\Money::format((float) $line->unit_cost * (float) $line->quantity) : '—' }}</td>@endif
                                </tr>
                            @endforeach
</x-table>
            </x-card>

            @if($cn->applications->isNotEmpty() || $cn->refunds->isNotEmpty())
                <x-card title="Where the credit went">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700 p-6 pt-3 text-sm text-gray-900 dark:text-gray-100">
                        @foreach($cn->applications as $application)
                            <li class="py-2 flex justify-between gap-3">
                                <span>{{ $application->applied_date->format('j M Y') }} · applied to invoice <a href="{{ route('invoices.show', $application->invoice) }}" class="text-brand-600 dark:text-brand-300">{{ $application->invoice->invoice_number }}</a></span>
                                <span class="font-medium">@money($application->amount)</span>
                            </li>
                        @endforeach
                        @foreach($cn->refunds as $refund)
                            <li class="py-2 flex justify-between gap-3">
                                <span>{{ $refund->refund_date->format('j M Y') }} · refunded to the customer{{ $refund->bank ? ' from '.$refund->bank->name : '' }}{{ $refund->reference ? ' ('.$refund->reference.')' : '' }}</span>
                                <span class="font-medium">@money($refund->amount)</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if($cn->journals->isNotEmpty())
                <x-card title="Ledger postings">
                    <div class="p-6 pt-3 space-y-4">
                        @foreach($cn->journals as $journal)
                            <div class="overflow-x-auto">
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

            @if($cn->notes)
                <x-card title="Notes"><p class="p-6 pt-3 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $cn->notes }}</p></x-card>
            @endif
        </div>
    </div>
</x-app-layout>
