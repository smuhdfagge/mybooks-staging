{{--
    The quotation page. The quotation itself, exactly as the customer gets
    it, sits on the left; what the business needs to act on (where it
    stands, next steps, what it became) sits on the right. Print prints
    just the quotation from this page; nothing opens in a new tab.
--}}
<x-app-layout>
    @php
        $state = $document['state'];
        $currency = $document['currency'];
        $money = fn ($value) => \App\Support\Money::format($value, $currency);
        $status = $quotation->status;
        $editable = in_array($status, \App\Enums\QuotationStatus::editableValues(), true);
        $convertible = in_array($status, \App\Enums\QuotationStatus::convertibleValues(), true);
        $answerable = in_array($status, ['draft', 'sent', 'expired'], true);
        $badge = ['sent' => 'unpaid', 'accepted' => 'paid', 'converted' => 'paid', 'rejected' => 'cancelled', 'expired' => 'overdue'][$state['key']] ?? $state['key'];
        $printIcon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>';
    @endphp

    <x-slot name="header">
        <x-table.page-header :title="'Quotation '.$quotation->quotation_number">
            <x-slot name="badge"><x-status-badge :status="$badge" :label="$state['label']" /></x-slot>
            <x-slot name="description">
                <a href="{{ route('customers.show', $quotation->customer) }}" class="link underline">{{ $quotation->customer->name }}</a>
                · {{ $quotation->quotation_date->format('j M Y') }}
                · {{ $money($quotation->total) }}
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('quotations.pdf', $quotation)">Download PDF</x-table.menu-item>
                @can('delete invoices')
                    @if (! in_array($status, ['accepted', 'converted'], true))
                        <x-table.menu-item danger :post="route('quotations.destroy', $quotation)" method="DELETE" confirm="Delete this quotation?">Delete</x-table.menu-item>
                    @endif
                @endcan
            </x-slot>
            <x-slot name="actions">
                <button type="button" class="btn-secondary gap-2" data-print data-testid="print-quotation">{!! $printIcon !!}<span>Print</span></button>
                @can('edit invoices')
                    @if ($editable)
                        <a href="{{ route('quotations.edit', $quotation) }}" class="btn-secondary">Edit</a>
                    @endif
                @endcan
                @can('send invoices')
                    @if (in_array($status, ['draft', 'sent'], true))
                        <button type="button" data-open-modal="send-quotation" class="btn-primary">{{ $status === 'sent' ? 'Email again' : 'Email to customer' }}</button>
                    @endif
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem] print:block">
        {{-- The quotation, on its sheet of paper --}}
        <div class="min-w-0 space-y-6 print:space-y-0">
            <section aria-label="The quotation as your customer sees it" class="rounded-xl bg-gray-100 px-0 py-3 sm:p-6 dark:bg-gray-950/60 print:rounded-none print:bg-white print:p-0">
                <div class="mx-auto max-w-[820px] overflow-hidden bg-white shadow-[0_1px_2px_rgba(16,42,67,0.08),0_16px_36px_-16px_rgba(16,42,67,0.35)] ring-1 ring-gray-900/5 sm:rounded-md print:max-w-none print:rounded-none print:shadow-none print:ring-0">
                    @include('quotations.document', $document)
                </div>
                <p class="mt-3 px-3 text-center text-xs text-gray-600 dark:text-gray-400 print:hidden">
                    This is what your customer receives. It becomes the invoice when they accept.
                    @can('view settings')<a href="{{ route('settings.invoice-templates.index') }}" class="link underline">Change its look</a>@endcan
                    @if (! $document['logo'] && $document['s']['show_logo'])
                        @can('edit settings')
                            <span class="mt-1 block" data-testid="add-logo-hint">Your logo is not on it yet. <a href="{{ route('settings.company') }}" class="link underline">Add your logo</a> and it will appear on every document.</span>
                        @endcan
                    @endif
                </p>
            </section>
        </div>

        {{-- What to act on --}}
        <aside class="space-y-4 lg:sticky lg:top-20 print:hidden" aria-label="Where this quotation stands">
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="quotation-total">
                <p class="text-sm text-gray-600 dark:text-gray-400">Quoted price</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums {{ in_array($state['key'], ['rejected', 'expired'], true) ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $money($quotation->total) }}</p>
                @if ($state['key'] === 'sent' && $state['daysLeft'] !== null)
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        @if ($state['daysLeft'] === 0)
                            This price holds until the end of today.
                        @else
                            This price holds for {{ $state['daysLeft'] }} more {{ \Illuminate\Support\Str::plural('day', $state['daysLeft']) }}, until {{ $quotation->expiry_date->format('j M Y') }}.
                        @endif
                    </p>
                @elseif ($state['expired'])
                    <p class="mt-2 text-sm font-medium text-red-700 dark:text-red-300">Expired on {{ $quotation->expiry_date?->format('j M Y') }}. Edit it to give a new date.</p>
                @elseif ($quotation->sent_at)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Sent {{ $quotation->sent_at->format('j M Y') }}.</p>
                @endif
            </section>

            {{-- Next steps --}}
            @if ($answerable || $convertible)
                <section class="space-y-3 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h3 class="font-medium text-gray-900 dark:text-white">Next steps</h3>
                    @can('edit invoices')
                        @if ($status === 'draft')
                            <form method="POST" action="{{ route('quotations.mark-sent', $quotation) }}">@csrf
                                <button type="submit" class="btn-secondary w-full justify-center">Mark as sent (handed over)</button>
                            </form>
                        @endif
                        @if ($answerable)
                            <p class="text-sm text-gray-600 dark:text-gray-400">What did the customer say?</p>
                            <div class="grid grid-cols-2 gap-2">
                                <form method="POST" action="{{ route('quotations.accept', $quotation) }}">@csrf
                                    <button type="submit" class="btn-primary w-full justify-center">Accepted</button>
                                </form>
                                <form method="POST" action="{{ route('quotations.reject', $quotation) }}" data-confirm="Mark this quotation as rejected by the customer?">@csrf
                                    <button type="submit" class="btn-secondary w-full justify-center">Declined</button>
                                </form>
                            </div>
                        @endif
                    @endcan
                    @if ($convertible)
                        @can('create sales-orders')
                            <form method="POST" action="{{ route('quotations.convert', $quotation) }}" data-confirm="Create a sales order from this quotation?">@csrf
                                <button type="submit" class="btn-primary w-full justify-center">Convert to sales order</button>
                            </form>
                        @endcan
                        @can('create invoices')
                            <form method="POST" action="{{ route('quotations.convert-invoice', $quotation) }}" data-confirm="Create a draft invoice from this quotation? Stock for the items is reserved.">@csrf
                                <button type="submit" class="btn-primary w-full justify-center">Convert to invoice</button>
                            </form>
                        @endcan
                    @endif
                </section>
            @endif

            {{-- What it became --}}
            @if ($quotation->salesOrder || $quotation->invoice)
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h3 class="font-medium text-gray-900 dark:text-white">Became</h3>
                    @if ($quotation->salesOrder)
                        <p class="mt-2 text-sm"><a href="{{ route('sales-orders.show', $quotation->salesOrder) }}" class="link">Sales order {{ $quotation->salesOrder->order_number }}</a></p>
                    @endif
                    @if ($quotation->invoice)
                        <p class="mt-2 text-sm"><a href="{{ route('invoices.show', $quotation->invoice) }}" class="link">Invoice {{ $quotation->invoice->invoice_number }}</a></p>
                    @endif
                </section>
            @endif

            {{-- Customer --}}
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                <h3 class="font-medium text-gray-900 dark:text-white">Customer</h3>
                <p class="mt-2 text-sm"><a href="{{ route('customers.show', $quotation->customer) }}" class="link">{{ $quotation->customer->name }}</a></p>
                @if ($quotation->customer->company_name && $quotation->customer->company_name !== $quotation->customer->name)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $quotation->customer->company_name }}</p>
                @endif
                <p class="truncate text-sm">
                    @if ($quotation->customer->email)
                        <a href="mailto:{{ $quotation->customer->email }}" class="link">{{ $quotation->customer->email }}</a>
                    @else
                        <span class="text-gray-600 dark:text-gray-400">No email address</span>
                    @endif
                </p>
                @if ($quotation->customer->phone)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $quotation->customer->phone }}</p>
                @endif
            </section>
        </aside>
    </div>

    @can('send invoices')
        <x-modal name="send-quotation" title="Email quotation" maxWidth="md">
            <form method="POST" action="{{ route('quotations.send', $quotation) }}" class="space-y-4 px-6 pb-6 pt-2">
                @csrf
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    The quotation goes to <strong>{{ $quotation->customer->email ?: 'the customer (no email address saved)' }}</strong> with a PDF copy attached.
                </p>
                <div><x-field name="message" label="Message (optional)" type="textarea" rows="3" /></div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-close-modal="send-quotation" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Send</button>
                </div>
            </form>
        </x-modal>
    @endcan

    @include('partials.invoice-print-page')
</x-app-layout>
