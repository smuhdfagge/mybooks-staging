{{--
    The purchase order page. The order itself, exactly as the supplier
    gets it, sits on the left; what the business needs to act on (where it
    stands, what arrived, the supplier) sits on the right. Print prints
    just the order from this page; nothing opens in a new tab.
--}}
<x-app-layout>
    @php
        $po = $purchaseOrder;
        $state = $document['state'];
        $currency = $document['currency'];
        $money = fn ($value) => \App\Support\Money::format($value, $currency);
        $billable = in_array($po->status, \App\Enums\PurchaseOrderStatus::billableValues(), true);
        $badge = ['billed' => 'paid', 'received' => 'paid', 'partially_received' => 'partial', 'confirmed' => 'unpaid', 'cancelled' => 'cancelled'][$state['key']] ?? 'draft';
        // How much of the order has actually arrived.
        $ordered = (float) $po->items->sum('quantity');
        $received = (float) $po->items->sum('quantity_received');
        $percent = $ordered > 0 ? (int) floor(min($received, $ordered) / $ordered * 100) : 0;
        $printIcon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>';
    @endphp

    <x-slot name="header">
        <x-table.page-header :title="'Purchase order '.$po->order_number">
            <x-slot name="badge"><x-status-badge :status="$badge" :label="$state['label']" /></x-slot>
            <x-slot name="description">
                <a href="{{ route('vendors.show', $po->vendor) }}" class="link underline">{{ $po->vendor->name }}</a>
                · Ordered {{ $po->order_date->format('j M Y') }}
                @if ($po->expected_date) · Wanted by {{ $po->expected_date->format('j M Y') }}@endif
                · {{ $money($po->total) }}
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('purchase-orders.pdf', $po)">Download PDF</x-table.menu-item>
                @if (in_array($po->status, ['draft', 'confirmed'], true))
                    @can('edit purchase-orders')
                        <x-table.menu-item danger :post="route('purchase-orders.cancel', $po)" confirm="Cancel this purchase order?">Cancel order</x-table.menu-item>
                    @endcan
                    @if ($po->status === 'draft')
                        @can('delete purchase-orders')
                            <x-table.menu-item danger :post="route('purchase-orders.destroy', $po)" method="DELETE" confirm="Delete this purchase order?">Delete</x-table.menu-item>
                        @endcan
                    @endif
                @endif
            </x-slot>
            <x-slot name="actions">
                <button type="button" class="btn-secondary gap-2" data-print data-testid="print-purchase-order">{!! $printIcon !!}<span>Print</span></button>
                @if ($po->status === 'draft')
                    @can('edit purchase-orders')
                        <a href="{{ route('purchase-orders.edit', $po) }}" class="btn-secondary">Edit</a>
                        <form action="{{ route('purchase-orders.confirm', $po) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-primary">Confirm order</button>
                        </form>
                    @endcan
                @elseif ($billable)
                    @can('create bills')
                        <form action="{{ route('purchase-orders.convert-to-bill', $po) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-primary">Convert to bill</button>
                        </form>
                    @endcan
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem] print:block">
        {{-- The order, on its sheet of paper --}}
        <div class="min-w-0 space-y-6 print:space-y-0">
            <section aria-label="The purchase order as your supplier sees it" class="rounded-xl bg-gray-100 px-0 py-3 sm:p-6 dark:bg-gray-950/60 print:rounded-none print:bg-white print:p-0">
                <div class="mx-auto max-w-[820px] overflow-hidden bg-white shadow-[0_1px_2px_rgba(16,42,67,0.08),0_16px_36px_-16px_rgba(16,42,67,0.35)] ring-1 ring-gray-900/5 sm:rounded-md print:max-w-none print:rounded-none print:shadow-none print:ring-0">
                    @include('purchase-orders.document', $document)
                </div>
                <p class="mt-3 px-3 text-center text-xs text-gray-600 dark:text-gray-400 print:hidden">
                    This is what your supplier receives. Print it, sign it, and send it with your order.
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
        <aside class="space-y-4 lg:sticky lg:top-20 print:hidden" aria-label="Order progress">
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10" data-testid="order-value">
                <p class="text-sm text-gray-600 dark:text-gray-400">Order value</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums {{ $state['key'] === 'cancelled' ? 'text-gray-500 line-through dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $money($po->total) }}</p>

                @if ($ordered > 0 && in_array($state['key'], ['confirmed', 'partially_received', 'received', 'billed'], true))
                    <div class="mt-4" role="img" aria-label="{{ $percent }}% received">
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full {{ $percent >= 100 ? 'bg-green-600 dark:bg-green-500' : 'bg-brand-600 dark:bg-brand-400' }}" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ \App\Support\Figure::qty($received) }} of {{ \App\Support\Figure::qty($ordered) }} received
                        </p>
                    </div>
                @endif

                @if ($state['key'] === 'draft')
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Not sent yet. Confirm it to send it to the supplier.</p>
                @elseif ($state['key'] === 'confirmed' && $po->expected_date)
                    <p class="mt-3 text-sm {{ $po->expected_date->lt(now()->startOfDay()) ? 'font-medium text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300' }}">
                        @if ($po->expected_date->lt(now()->startOfDay()))
                            Was wanted by {{ $po->expected_date->format('j M Y') }}. Nothing has arrived.
                        @else
                            Wanted by {{ $po->expected_date->format('j M Y') }}.
                        @endif
                    </p>
                @endif

                @if ($po->status === 'draft')
                    @can('edit purchase-orders')
                        <form action="{{ route('purchase-orders.confirm', $po) }}" method="POST" class="mt-4">
                            @csrf
                            <button type="submit" class="btn-primary w-full justify-center">Confirm order</button>
                        </form>
                    @endcan
                @elseif ($billable)
                    @can('create bills')
                        <form action="{{ route('purchase-orders.convert-to-bill', $po) }}" method="POST" class="mt-4">
                            @csrf
                            <button type="submit" class="btn-primary w-full justify-center">Convert to bill</button>
                        </form>
                    @endcan
                @endif
            </section>

            {{-- Bills raised from this order --}}
            @if ($po->bills->isNotEmpty())
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                    <h2 class="section-title">Billed</h2>
                    @foreach ($po->bills as $bill)
                        <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <a href="{{ route('bills.show', $bill) }}" class="link">{{ $bill->bill_number }}</a>
                                <p class="text-gray-600 dark:text-gray-400">{{ $bill->bill_date?->format('j M Y') }}</p>
                            </div>
                            <p class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $money($bill->total) }}</p>
                        </div>
                    @endforeach
                </section>
            @endif

            {{-- Supplier --}}
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                <h2 class="section-title">Supplier</h2>
                <p class="mt-2 text-sm"><a href="{{ route('vendors.show', $po->vendor) }}" class="link">{{ $po->vendor->name }}</a></p>
                @if ($po->vendor->company_name && $po->vendor->company_name !== $po->vendor->name)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $po->vendor->company_name }}</p>
                @endif
                @if ($po->vendor->email)
                    <p class="truncate text-sm"><a href="mailto:{{ $po->vendor->email }}" class="link">{{ $po->vendor->email }}</a></p>
                @endif
                @if ($po->vendor->phone)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $po->vendor->phone }}</p>
                @endif
            </section>

            {{-- Activity --}}
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                <h2 class="section-title">Activity</h2>
                <ol class="mt-3 space-y-3 border-l border-gray-200 pl-4 dark:border-gray-700">
                    <li class="relative text-sm">
                        <span class="absolute -left-[1.3rem] top-1.5 h-2 w-2 rounded-full bg-gray-400 ring-4 ring-white dark:bg-gray-500 dark:ring-gray-800" aria-hidden="true"></span>
                        <p class="text-gray-900 dark:text-gray-100">Raised</p>
                        <p class="text-gray-600 dark:text-gray-400">
                            <time datetime="{{ $po->created_at?->toIso8601String() }}">{{ $po->created_at?->format('j M Y') }}</time>@if ($po->createdBy), {{ $po->createdBy->name }}@endif
                        </p>
                    </li>
                    @if ($po->updated_at->gt($po->created_at->copy()->addMinute()))
                        <li class="relative text-sm">
                            <span class="absolute -left-[1.3rem] top-1.5 h-2 w-2 rounded-full bg-gray-400 ring-4 ring-white dark:bg-gray-500 dark:ring-gray-800" aria-hidden="true"></span>
                            <p class="text-gray-900 dark:text-gray-100">Last changed</p>
                            <p class="text-gray-600 dark:text-gray-400"><time datetime="{{ $po->updated_at->toIso8601String() }}">{{ $po->updated_at->format('j M Y') }}</time></p>
                        </li>
                    @endif
                </ol>
            </section>
        </aside>
    </div>

    @include('partials.invoice-print-page')
</x-app-layout>
