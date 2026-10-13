{{-- Stock transfers list (tables plan T4). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $st = fn ($t) => $t->status instanceof \BackedEnum ? $t->status->value : $t->status;
    $label = fn ($t) => null;
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, reference or item" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="warehouse" label="Warehouse" :options="$warehouses" />
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($transfers->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No transfers match these filters" />
                @else
                    <x-table.empty title="No stock transfers yet" text="Move stock from one warehouse to another. It leaves when you ship it and arrives when you receive it.">
                        <a href="{{ route('stock-transfers.create') }}" class="btn-new">New transfer</a>
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Stock transfers" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="transfer_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th field="transfer_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th>From</x-table.th>
                    <x-table.th>To</x-table.th>
                    <x-table.th num>Lines</x-table.th>
                    <x-table.th num>Value</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($transfers as $transfer)
                    <tr wire:key="st-{{ $transfer->id }}">
                        <td>
                            <a href="{{ route('stock-transfers.show', $transfer) }}" class="tbl-link">{{ $transfer->transfer_number }}</a>
                            @if ($transfer->reference)<div class="text-xs tbl-muted">{{ $transfer->reference }}</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $date($transfer->transfer_date) }}</td>
                        <td class="max-w-[12rem] truncate">{{ $transfer->fromWarehouse?->name ?? '—' }}</td>
                        <td class="max-w-[12rem] truncate">{{ $transfer->toWarehouse?->name ?? '—' }}</td>
                        <td class="num">{{ number_format($transfer->items_count) }}</td>
                        <td class="num {{ $st($transfer) === 'draft' || ! $transfer->items_sum_shipped_cost ? 'tbl-zero' : '' }}">{{ $st($transfer) === 'draft' || ! $transfer->items_sum_shipped_cost ? '—' : $money($transfer->items_sum_shipped_cost) }}</td>
                        <td><x-status-badge :status="$st($transfer)" :label="$label($transfer)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$transfer->transfer_number">
                                <x-table.menu-item :href="route('stock-transfers.show', $transfer)">{{ in_array($st($transfer), ['draft', 'in_transit'], true) ? 'View, ship or receive' : 'View' }}</x-table.menu-item>
                                <x-table.menu-item :href="route('stock-transfers.print', $transfer)" new-tab>Print</x-table.menu-item>
                                @if ($st($transfer) === 'draft')<x-table.menu-item :href="route('stock-transfers.edit', $transfer)">Edit</x-table.menu-item>@endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Stock transfers">
                @foreach ($transfers as $transfer)
                    <li wire:key="st-card-{{ $transfer->id }}">
                        <x-table.card :href="route('stock-transfers.show', $transfer)" :title="($transfer->fromWarehouse?->name ?? '—').' to '.($transfer->toWarehouse?->name ?? '—')"
                            :meta="$transfer->transfer_number.' · '.$date($transfer->transfer_date).' · '.$transfer->items_count.' '.($transfer->items_count == 1 ? 'line' : 'lines')">
                            <x-slot name="badge"><x-status-badge :status="$st($transfer)" :label="$label($transfer)" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$transfers" />
</div>
