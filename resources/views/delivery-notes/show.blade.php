{{--
    The delivery note page. The note itself, exactly as it goes out with
    the driver, sits on the left; what the business needs to act on
    (dispatch, confirm receipt, customer) sits on the right. Print prints
    just the note from this page; nothing opens in a new tab.
--}}
<x-app-layout>
    @php
        $note = $deliveryNote;
        $state = $document['state'];
        $status = $note->status;
        $badge = ['delivered' => 'paid', 'in_transit' => 'partial', 'dispatched' => 'unpaid', 'cancelled' => 'cancelled'][$state['key']] ?? 'draft';
        $printIcon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>';
    @endphp

    <x-slot name="header">
        <x-table.page-header :title="'Delivery note '.$note->delivery_number">
            <x-slot name="badge"><x-status-badge :status="$badge" :label="$state['label']" /></x-slot>
            <x-slot name="description">
                {{ $note->customer->name }} · {{ $note->delivery_date->format('j M Y') }}
                @if ($note->salesOrder) · for order <a href="{{ route('sales-orders.show', $note->salesOrder) }}" class="link underline">{{ $note->salesOrder->order_number }}</a>@endif
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('delivery-notes.pdf', $note)">Download PDF</x-table.menu-item>
                @can('delete invoices')
                    @if (in_array($status, ['draft', 'cancelled'], true))
                        <x-table.menu-item danger :post="route('delivery-notes.destroy', $note)" method="DELETE" confirm="Delete this delivery note?">Delete</x-table.menu-item>
                    @endif
                @endcan
            </x-slot>
            <x-slot name="actions">
                <button type="button" class="btn-secondary gap-2" data-print data-testid="print-delivery-note">{!! $printIcon !!}<span>Print</span></button>
                @can('edit invoices')
                    @if ($status === 'draft')
                        <form method="POST" action="{{ route('delivery-notes.dispatch', $note) }}">@csrf
                            <button type="submit" class="btn-primary">Dispatch (goods leave)</button>
                        </form>
                    @endif
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem] print:block">
        {{-- The delivery note, on its sheet of paper --}}
        <div class="min-w-0 space-y-6 print:space-y-0">
            <section aria-label="The delivery note as it goes out with the driver" class="rounded-xl bg-gray-100 px-0 py-3 sm:p-6 dark:bg-gray-950/60 print:rounded-none print:bg-white print:p-0">
                <div class="mx-auto max-w-[820px] overflow-hidden bg-white shadow-[0_1px_2px_rgba(16,42,67,0.08),0_16px_36px_-16px_rgba(16,42,67,0.35)] ring-1 ring-gray-900/5 sm:rounded-md print:max-w-none print:rounded-none print:shadow-none print:ring-0">
                    @include('delivery-notes.document', $document)
                </div>
                <p class="mt-3 px-3 text-center text-xs text-gray-600 dark:text-gray-400 print:hidden">
                    Print two copies: one the customer signs and the driver brings back, one they keep. There are no prices on it.
                    @can('view settings')<a href="{{ route('settings.invoice-templates.index') }}" class="link underline">Change its look</a>@endcan
                </p>
            </section>

            <div class="rounded-lg bg-white p-5 text-sm text-gray-700 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10 print:hidden">
                Delivery notes don't change stock. Stock comes out when the invoice is released (waybill).
                @if ($warehouseName = \App\Models\Warehouse::nameIfMany($note->warehouse_id)) These goods are going from {{ $warehouseName }}. @endif
            </div>
        </div>

        {{-- What to act on --}}
        <aside class="space-y-4 lg:sticky lg:top-20 print:hidden" aria-label="Delivery progress">
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="delivery-state">
                <p class="text-sm text-gray-600 dark:text-gray-400">Where it is</p>
                <p class="mt-1 text-2xl font-semibold {{ $state['key'] === 'cancelled' ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $state['label'] }}</p>
                @if ($document['short'])
                    <p class="mt-3 text-sm font-medium text-red-700 dark:text-red-300" data-testid="short-delivery">Some items are going out short of what was ordered.</p>
                @endif
                @if ($note->dispatched_at)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Left {{ $note->dispatched_at->format('j M Y, H:i') }}.</p>
                @endif
                @if ($note->received_by)
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Signed for by {{ $note->received_by }}@if ($note->received_at) on {{ $note->received_at->format('j M Y') }}@endif.</p>
                @endif

                @can('edit invoices')
                    @if ($status === 'draft')
                        <form method="POST" action="{{ route('delivery-notes.dispatch', $note) }}" class="mt-4">@csrf
                            <button type="submit" class="btn-primary w-full justify-center">Dispatch (goods leave)</button>
                        </form>
                    @elseif (in_array($status, ['dispatched', 'in_transit'], true))
                        <form method="POST" action="{{ route('delivery-notes.confirm', $note) }}" class="mt-4 space-y-2">@csrf
                            <x-field name="received_by" label="Received by" required placeholder="Name of the person who signed" />
                            <button type="submit" class="btn-primary w-full justify-center">Confirm delivered</button>
                        </form>
                    @endif
                    @if (in_array($status, ['draft', 'dispatched', 'in_transit'], true))
                        <form method="POST" action="{{ route('delivery-notes.cancel', $note) }}" class="mt-2" data-confirm="Cancel this delivery note?{{ $status !== 'draft' ? ' Its quantities come off the sales order again.' : '' }}">@csrf
                            <button type="submit" class="btn-secondary w-full justify-center">Cancel delivery note</button>
                        </form>
                    @endif
                @endcan
            </section>

            {{-- How it is travelling --}}
            @if ($note->shipping_method || $note->tracking_number)
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h3 class="font-medium text-gray-900 dark:text-white">Transport</h3>
                    @if ($note->shipping_method)
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $note->shipping_method }}</p>
                    @endif
                    @if ($note->tracking_number)
                        <p class="text-sm text-gray-600 dark:text-gray-400">Vehicle or tracking: {{ $note->tracking_number }}</p>
                    @endif
                </section>
            @endif

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                <h3 class="font-medium text-gray-900 dark:text-white">Customer</h3>
                <p class="mt-2 text-sm text-gray-900 dark:text-gray-100">{{ $note->customer->name }}</p>
                @if ($note->customer->phone)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $note->customer->phone }}</p>
                @endif
                @if ($note->salesOrder)
                    @can('view sales-orders')
                        <p class="mt-2 text-sm"><a href="{{ route('sales-orders.show', $note->salesOrder) }}" class="link">Sales order {{ $note->salesOrder->order_number }}</a></p>
                    @endcan
                @endif
            </section>
        </aside>
    </div>

    @include('partials.invoice-print-page')
</x-app-layout>
