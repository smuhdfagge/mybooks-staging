{{-- Delivery notes list (tables plan T2). No amounts: a delivery note is paperwork. --}}
@php
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, order, tracking or customer" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="customer" label="Customer" :options="$customers" />
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($notes->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No delivery notes match these filters" />
                @else
                    <x-table.empty title="No delivery notes yet" text="Make one from a sales order when the goods leave.">
                        @can('create invoices')<a href="{{ route('delivery-notes.create') }}" class="btn-new">New delivery note</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Delivery notes" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="delivery_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Customer</x-table.th>
                    <x-table.th>Sales order</x-table.th>
                    <x-table.th field="delivery_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th>Shipping</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($notes as $note)
                    <tr wire:key="dn-{{ $note->id }}">
                        <td><a href="{{ route('delivery-notes.show', $note) }}" class="tbl-link">{{ $note->delivery_number }}</a></td>
                        <td class="max-w-[16rem] truncate">{{ $note->customer?->name ?? '—' }}</td>
                        <td>
                            @if ($note->salesOrder)
                                <a href="{{ route('sales-orders.show', $note->salesOrder) }}" class="text-brand-700 hover:underline dark:text-brand-300">{{ $note->salesOrder->order_number }}</a>
                            @else
                                <span class="tbl-zero">—</span>
                            @endif
                        </td>
                        <td class="tbl-muted">{{ $date($note->delivery_date) }}</td>
                        <td class="max-w-[14rem] truncate tbl-muted">{{ collect([$note->shipping_method, $note->tracking_number])->filter()->join(' · ') ?: '—' }}</td>
                        <td><x-status-badge :status="$note->status instanceof \BackedEnum ? $note->status->value : $note->status" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$note->delivery_number">
                                <x-table.menu-item :href="route('delivery-notes.show', $note)">View</x-table.menu-item>
                                <x-table.menu-item :href="route('delivery-notes.print', $note)" new-tab>Print</x-table.menu-item>
                                <x-table.menu-item :href="route('delivery-notes.pdf', $note)">Download PDF</x-table.menu-item>
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Delivery notes">
                @foreach ($notes as $note)
                    <li wire:key="dn-card-{{ $note->id }}">
                        <x-table.card :href="route('delivery-notes.show', $note)" :title="$note->customer?->name ?? '—'"
                            :meta="$note->delivery_number.' · '.$date($note->delivery_date).($note->salesOrder ? ' · '.$note->salesOrder->order_number : '')">
                            <x-slot name="badge"><x-status-badge :status="$note->status instanceof \BackedEnum ? $note->status->value : $note->status" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$notes" />
</div>
