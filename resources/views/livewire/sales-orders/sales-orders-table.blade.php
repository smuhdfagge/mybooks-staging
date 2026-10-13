{{-- Sales orders list (tables plan T2). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $st = fn ($o) => $o->status instanceof \BackedEnum ? $o->status->value : $o->status;
    $canBulk = $user->canAny(['edit sales-orders', 'delete sales-orders']);
    $ids = $orders->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, reference or customer" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="customer" label="Customer" :options="$customers" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit sales-orders')
                    <x-table.bulk-button action="confirm" confirm="Confirm the ticked draft orders?">Confirm</x-table.bulk-button>
                    <x-table.bulk-button action="cancel" confirm="Cancel the ticked orders? Only draft and confirmed orders change.">Cancel</x-table.bulk-button>
                @endcan
                @can('delete sales-orders')<x-table.bulk-button action="delete" danger confirm="Delete the ticked orders? Orders with invoices or delivery notes are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$orders" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($orders->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No sales orders match these filters" />
                @else
                    <x-table.empty title="No sales orders yet" text="Record what a customer has ordered, then deliver and invoice it.">
                        @can('create sales-orders')<a href="{{ route('sales-orders.create') }}" class="btn-new">New sales order</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Sales orders" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every order on this page" />@endif
                    <x-table.th field="order_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Customer</x-table.th>
                    <x-table.th field="order_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="expected_date" :sort="[$sortField, $sortDirection]">Expected</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($orders as $order)
                    @php
                        $ticked = in_array((string) $order->id, $selectedItems, true);
                        $late = in_array($st($order), ['confirmed', 'processing'], true) && $order->expected_date && $order->expected_date->lt(today());
                    @endphp
                    <tr wire:key="so-{{ $order->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$order->id" :label="$order->order_number" />@endif
                        <td>
                            <a href="{{ route('sales-orders.show', $order) }}" class="tbl-link">{{ $order->order_number }}</a>
                            @if ($order->reference)<div class="text-xs tbl-muted">{{ $order->reference }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $order->customer?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($order->order_date) }}</td>
                        <td class="{{ $late ? 'tbl-late' : 'tbl-muted' }}">{{ $date($order->expected_date) }}</td>
                        <td class="num">{{ $money($order->total) }}</td>
                        <td><x-status-badge :status="$st($order)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$order->order_number">
                                <x-table.menu-item :href="route('sales-orders.show', $order)">View</x-table.menu-item>
                                @if ($st($order) === 'draft' && $user->can('edit sales-orders'))
                                    <x-table.menu-item :post="route('sales-orders.confirm', $order)">Confirm</x-table.menu-item>
                                    <x-table.menu-item :href="route('sales-orders.edit', $order)">Edit</x-table.menu-item>
                                @endif
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
            <ul class="space-y-2 md:hidden" aria-label="Sales orders">
                @foreach ($orders as $order)
                    <li wire:key="so-card-{{ $order->id }}">
                        <x-table.card :href="route('sales-orders.show', $order)" :title="$order->customer?->name ?? '—'" :amount="\App\Support\Money::format($order->total)"
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
