{{-- Supplier advances list (tables plan T3): money paid before the bill. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, reference or vendor" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="vendor" label="Vendor" :options="$vendors" />
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($advances->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No advances match these filters" />
                @else
                    <x-table.empty title="No advances yet" text="When you pay a vendor before their bill, record it here. Use it against the bill when it comes.">
                        @can('create payments-made')<a href="{{ route('supplier-advances.create') }}" class="btn-new">Pay an advance</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Supplier advances" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="payment_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Vendor</x-table.th>
                    <x-table.th field="payment_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="amount" :sort="[$sortField, $sortDirection]" num>Paid</x-table.th>
                    <x-table.th field="unused_amount" :sort="[$sortField, $sortDirection]" num>Not yet used</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($advances as $advance)
                    <tr wire:key="adv-{{ $advance->id }}">
                        <td>
                            <a href="{{ route('supplier-advances.show', $advance) }}" class="tbl-link">{{ $advance->payment_number }}</a>
                            @if ($advance->reference)<div class="text-xs tbl-muted">{{ $advance->reference }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $advance->vendor?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($advance->payment_date) }}</td>
                        <td class="num">{{ $money($advance->amount) }}</td>
                        <td class="num {{ $advance->unused_amount > 0 ? '' : 'tbl-zero' }}">{{ $advance->unused_amount > 0 ? $money($advance->unused_amount) : '—' }}</td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$advance->payment_number">
                                <x-table.menu-item :href="route('supplier-advances.show', $advance)">{{ $advance->unused_amount > 0 ? 'View or use against a bill' : 'View' }}</x-table.menu-item>
                                <x-table.menu-item :href="route('payments-made.show', $advance)">Payment details</x-table.menu-item>
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="3">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'advance' : 'advances' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->amount) }}</td>
                        <td class="num">{{ $money($totals->unused) }}</td>
                        <td></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Supplier advances">
                @foreach ($advances as $advance)
                    <li wire:key="adv-card-{{ $advance->id }}">
                        <x-table.card :href="route('supplier-advances.show', $advance)" :title="$advance->vendor?->name ?? '—'" :amount="\App\Support\Money::format($advance->amount)"
                            :meta="$advance->payment_number.' · '.$date($advance->payment_date)">
                            @if ($advance->unused_amount > 0)
                                <x-slot name="alert">{{ \App\Support\Money::format($advance->unused_amount) }} not yet used</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$advances" />
</div>
