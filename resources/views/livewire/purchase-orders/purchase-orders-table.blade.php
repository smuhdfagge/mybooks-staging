{{-- Purchase orders list (tables plan T3). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $st = fn ($o) => $o->status instanceof \BackedEnum ? $o->status->value : $o->status;
    $canBulk = $user->canAny(['edit purchase-orders', 'delete purchase-orders']);
    $ids = $orders->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, reference or vendor" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="vendor" label="Vendor" :options="$vendors" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit purchase-orders')
                    <x-table.bulk-button action="confirm" confirm="Confirm the ticked draft orders?">Confirm</x-table.bulk-button>
                    <x-table.bulk-button action="cancel" confirm="Cancel the ticked orders? Only draft and confirmed orders change.">Cancel</x-table.bulk-button>
                @endcan
                @can('delete purchase-orders')<x-table.bulk-button action="delete" danger confirm="Delete the ticked orders? Orders with bills are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$orders" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($orders->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No purchase orders match these filters" />
                @else
                    <x-table.empty title="No purchase orders yet" text="Send a vendor an order, then turn it into a bill when the goods come.">
                        @can('create purchase-orders')<a href="{{ route('purchase-orders.create') }}" class="btn-new">New purchase order</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Purchase orders" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every order on this page" />@endif
                    <x-table.th field="order_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Vendor</x-table.th>
                    <x-table.th field="order_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="expected_date" :sort="[$sortField, $sortDirection]">Expected</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($orders as $order)
                    @php
                        $ticked = in_array((string) $order->id, $selectedItems, true);
                        $late = in_array($st($order), ['confirmed', 'partially_received'], true) && $order->expected_date && $order->expected_date->lt(today());
                    @endphp
                    <tr wire:key="po-{{ $order->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$order->id" :label="$order->order_number" />@endif
                        <td>
                            <a href="{{ route('purchase-orders.show', $order) }}" class="tbl-link">{{ $order->order_number }}</a>
                            @if ($order->reference)<div class="text-xs tbl-muted">{{ $order->reference }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $order->vendor?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($order->order_date) }}</td>
                        <td class="{{ $late ? 'tbl-late' : 'tbl-muted' }}">{{ $date($order->expected_date) }}</td>
                        <td class="num">{{ $money($order->total) }}</td>
                        <td><x-status-badge :status="$st($order)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$order->order_number">
                                <x-table.menu-item :href="route('purchase-orders.show', $order)">View</x-table.menu-item>
                                @can('edit purchase-orders')
                                    @if ($st($order) === 'draft')
                                        <x-table.menu-item :post="route('purchase-orders.confirm', $order)">Confirm</x-table.menu-item>
                                        <x-table.menu-item :href="route('purchase-orders.edit', $order)">Edit</x-table.menu-item>
                                    @endif
                                    @if (in_array($st($order), \App\Models\PurchaseOrder::BILLABLE, true) && $user->can('create bills'))
                                        <x-table.menu-item :post="route('purchase-orders.convert-to-bill', $order)">Make a bill</x-table.menu-item>
                                    @endif
                                @endcan
                                @can('delete purchase-orders')
                                    @if (in_array($st($order), ['draft', 'cancelled'], true))
                                        <x-table.menu-item wire="deleteOne({{ $order->id }})" :confirm="'Delete '.$order->order_number.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                    @endif
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'order' : 'orders' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Purchase orders">
                @foreach ($orders as $order)
                    <li wire:key="po-card-{{ $order->id }}">
                        <x-table.card :href="route('purchase-orders.show', $order)" :title="$order->vendor?->name ?? '—'" :amount="\App\Support\Money::format($order->total)"
                            :meta="$order->order_number.' · '.$date($order->order_date)">
                            <x-slot name="badge"><x-status-badge :status="$st($order)" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Total {{ \App\Support\Money::format($totals->total) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$orders" />
</div>
