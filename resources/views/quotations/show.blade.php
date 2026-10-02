@php
    $btn = 'inline-flex items-center justify-center px-3 py-2 border rounded-md font-semibold text-xs uppercase tracking-widest transition';
    $secondary = $btn.' bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600';
    $status = $quotation->status;
    $editable = in_array($status, \App\Enums\QuotationStatus::editableValues(), true);
    $convertible = in_array($status, \App\Enums\QuotationStatus::convertibleValues(), true);
    $answerable = in_array($status, ['draft', 'sent', 'expired'], true);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Quotation {{ $quotation->quotation_number }}</h2>
                <x-status-badge :status="$status" />
                @if($status !== 'expired' && $quotation->isExpired())
                    <x-status-badge status="expired" label="Past expiry date" />
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="{{ $secondary }}">Print</a>
                <a href="{{ route('quotations.pdf', $quotation) }}" class="{{ $secondary }}">PDF</a>
                @can('edit invoices')
                    @if($editable)
                        <a href="{{ route('quotations.edit', $quotation) }}" class="{{ $secondary }}">Edit</a>
                    @endif
                @endcan
                @can('send invoices')
                    @if(in_array($status, ['draft', 'sent'], true))
                        <button type="button" data-open-modal="send-quotation" class="btn-primary">{{ $status === 'sent' ? 'Email again' : 'Email to customer' }}</button>
                    @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <x-card>
                        <div class="p-4 sm:p-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div><p class="text-gray-500 dark:text-gray-400">Date</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $quotation->quotation_date->format('M d, Y') }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Valid until</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $quotation->expiry_date?->format('M d, Y') ?? '—' }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Reference</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $quotation->reference ?: '—' }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Sent</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $quotation->sent_at?->format('M d, Y') ?? '—' }}</p></div>
                        </div>
                    </x-card>

                    <x-card>
                        <div class="p-4 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Items</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead>
                                        <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                            <th class="px-3 py-2 text-left">Description</th>
                                            <th class="px-3 py-2 text-right">Qty</th>
                                            <th class="px-3 py-2 text-right">Price</th>
                                            <th class="px-3 py-2 text-right">VAT</th>
                                            <th class="px-3 py-2 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                        @foreach($quotation->items as $line)
                                            <tr>
                                                <td class="px-3 py-2">{{ $line->description }}@if($line->item && $line->item->name !== $line->description)<span class="block text-xs text-gray-500">{{ $line->item->name }}</span>@endif</td>
                                                <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                                                <td class="px-3 py-2 text-right">@money($line->unit_price)</td>
                                                <td class="px-3 py-2 text-right">{{ (float) $line->tax_rate }}%</td>
                                                <td class="px-3 py-2 text-right font-medium">@money($line->total)</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 ml-auto max-w-xs space-y-1 text-sm">
                                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Subtotal</span><span class="text-gray-900 dark:text-gray-100">@money($quotation->subtotal)</span></div>
                                @if((float) $quotation->discount_amount > 0)
                                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Discount</span><span class="text-red-600 dark:text-red-400">-@money($quotation->discount_amount)</span></div>
                                @endif
                                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">VAT</span><span class="text-gray-900 dark:text-gray-100">@money($quotation->tax_amount)</span></div>
                                <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2 text-base font-bold"><span class="text-gray-900 dark:text-gray-100">Total</span><span class="text-indigo-600 dark:text-indigo-400">@money($quotation->total)</span></div>
                            </div>
                        </div>
                    </x-card>

                    @if($quotation->notes || $quotation->terms)
                        <x-card>
                            <div class="p-4 sm:p-6 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                                @if($quotation->notes)<div><p class="font-medium text-gray-900 dark:text-gray-100">Notes</p><p class="whitespace-pre-line">{{ $quotation->notes }}</p></div>@endif
                                @if($quotation->terms)<div><p class="font-medium text-gray-900 dark:text-gray-100">Terms</p><p class="whitespace-pre-line">{{ $quotation->terms }}</p></div>@endif
                            </div>
                        </x-card>
                    @endif
                </div>

                <div class="space-y-6">
                    <x-card>
                        <div class="p-4 sm:p-6 text-sm space-y-1">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Customer</h3>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $quotation->customer->name }}</p>
                            @if($quotation->customer->company_name)<p class="text-gray-600 dark:text-gray-400">{{ $quotation->customer->company_name }}</p>@endif
                            <p class="text-gray-600 dark:text-gray-400">{{ $quotation->customer->email ?: 'No email address' }}</p>
                            @if($quotation->customer->phone)<p class="text-gray-600 dark:text-gray-400">{{ $quotation->customer->phone }}</p>@endif
                        </div>
                    </x-card>

                    @if($quotation->salesOrder || $quotation->invoice)
                        <x-card>
                            <div class="p-4 sm:p-6 text-sm">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Converted to</h3>
                                @if($quotation->salesOrder)
                                    <a href="{{ route('sales-orders.show', $quotation->salesOrder) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Sales order {{ $quotation->salesOrder->order_number }}</a>
                                @endif
                                @if($quotation->invoice)
                                    <a href="{{ route('invoices.show', $quotation->invoice) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Invoice {{ $quotation->invoice->invoice_number }}</a>
                                @endif
                            </div>
                        </x-card>
                    @endif

                    <x-card>
                        <div class="p-4 sm:p-6 space-y-3">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Next steps</h3>
                            @can('edit invoices')
                                @if($status === 'draft')
                                    <form method="POST" action="{{ route('quotations.mark-sent', $quotation) }}">@csrf
                                        <button type="submit" class="{{ $secondary }} w-full">Mark as sent (handed over)</button>
                                    </form>
                                @endif
                                @if($answerable)
                                    <div class="grid grid-cols-2 gap-2">
                                        <form method="POST" action="{{ route('quotations.accept', $quotation) }}">@csrf
                                            <button type="submit" class="{{ $btn }} w-full bg-green-600 border-transparent text-white hover:bg-green-700">Accepted</button>
                                        </form>
                                        <form method="POST" action="{{ route('quotations.reject', $quotation) }}" data-confirm="Mark this quotation as rejected by the customer?">@csrf
                                            <button type="submit" class="{{ $btn }} w-full bg-red-600 border-transparent text-white hover:bg-red-700">Rejected</button>
                                        </form>
                                    </div>
                                @endif
                            @endcan
                            @if($convertible)
                                @can('create sales-orders')
                                    <form method="POST" action="{{ route('quotations.convert', $quotation) }}" data-confirm="Create a sales order from this quotation?">@csrf
                                        <button type="submit" class="btn-primary w-full">Convert to sales order</button>
                                    </form>
                                @endcan
                                @can('create invoices')
                                    <form method="POST" action="{{ route('quotations.convert-invoice', $quotation) }}" data-confirm="Create a draft invoice from this quotation? Stock for the items is reserved.">@csrf
                                        <button type="submit" class="btn-primary w-full">Convert to invoice</button>
                                    </form>
                                @endcan
                            @elseif($status === 'expired')
                                <p class="text-sm text-gray-600 dark:text-gray-400">This quotation has expired. Edit it to give a new date, or mark it accepted if the customer has agreed.</p>
                            @endif
                            @can('delete invoices')
                                @if(! in_array($status, ['accepted', 'converted'], true))
                                    <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" data-confirm="Delete this quotation?">@csrf @method('DELETE')
                                        <button type="submit" class="{{ $btn }} w-full bg-white dark:bg-gray-800 border-red-300 text-red-700 dark:text-red-400 hover:bg-red-50">Delete</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </x-card>
                </div>
            </div>
        </div>
    </div>

    @can('send invoices')
        <x-modal name="send-quotation" title="Email quotation" maxWidth="md">
            <form method="POST" action="{{ route('quotations.send', $quotation) }}" class="p-6 space-y-4">
                @csrf
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    The quotation goes to <strong>{{ $quotation->customer->email ?: 'the customer (no email address saved)' }}</strong> with a PDF copy attached.
                </p>
                <div><x-field name="message" label="Message (optional)" type="textarea" rows="3" /></div>
                <div class="flex justify-end gap-3">
                    <button type="button" data-close-modal="send-quotation" class="{{ $secondary }}">Cancel</button>
                    <button type="submit" class="btn-primary">Send</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
