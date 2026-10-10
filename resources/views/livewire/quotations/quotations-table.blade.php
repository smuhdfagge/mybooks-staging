{{-- Quotations list (tables plan T2). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $today = now()->startOfDay();
    $lapsed = fn ($q) => in_array($q->status, ['draft', 'sent'], true) && $q->expiry_date && $q->expiry_date->lt($today);
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, reference or customer" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="customer" label="Customer" :options="$customers" />
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($quotations->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No quotations match these filters" />
                @else
                    <x-table.empty title="No quotations yet" text="A quotation tells a customer what you would charge, before they agree.">
                        @can('create invoices')<a href="{{ route('quotations.create') }}" class="btn-new">New quotation</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Quotations" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="quotation_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Customer</x-table.th>
                    <x-table.th field="quotation_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="expiry_date" :sort="[$sortField, $sortDirection]">Valid until</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($quotations as $q)
                    <tr wire:key="qt-{{ $q->id }}">
                        <td>
                            <a href="{{ route('quotations.show', $q) }}" class="tbl-link">{{ $q->quotation_number }}</a>
                            @if ($q->reference)<span class="ml-1.5 text-xs tbl-muted">{{ $q->reference }}</span>@endif
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $q->customer?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($q->quotation_date) }}</td>
                        <td class="{{ $lapsed($q) ? 'tbl-late' : 'tbl-muted' }}">{{ $date($q->expiry_date) }}</td>
                        <td class="num">{{ $money($q->total) }}</td>
                        <td><x-status-badge :status="$q->status" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$q->quotation_number">
                                <x-table.menu-item :href="route('quotations.show', $q)">View</x-table.menu-item>
                                <x-table.menu-item :href="route('quotations.print', $q)" new-tab>Print</x-table.menu-item>
                                <x-table.menu-item :href="route('quotations.pdf', $q)">Download PDF</x-table.menu-item>
                                @if (in_array($q->status, ['draft', 'sent'], true) && $user->can('edit invoices'))
                                    <x-table.menu-item :href="route('quotations.edit', $q)">Edit</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'quotation' : 'quotations' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Quotations">
                @foreach ($quotations as $q)
                    <li wire:key="qt-card-{{ $q->id }}">
                        <x-table.card :href="route('quotations.show', $q)" :title="$q->customer?->name ?? '—'" :amount="\App\Support\Money::format($q->total)"
                            :meta="$q->quotation_number.' · '.$date($q->quotation_date)" :tone="$lapsed($q) ? 'bad' : 'muted'">
                            <x-slot name="badge"><x-status-badge :status="$q->status" /></x-slot>
                            @if ($lapsed($q))
                                <x-slot name="alert">Ran out on {{ $date($q->expiry_date) }}</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$quotations" />
</div>
