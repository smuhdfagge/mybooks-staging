{{-- Supplier credits list (tables plan T3). Mirrors the customer Credit notes list. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $st = fn ($c) => $c->status instanceof \BackedEnum ? $c->status->value : $c->status;
    $label = fn ($c) => $st($c) === 'closed' ? 'Used up' : null;
    $left = fn ($c) => $st($c) === 'open' && $c->balance > 0;
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, vendor's reference, bill or vendor" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="vendor" label="Vendor" :options="$vendors" />
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($credits->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No supplier credits match these filters" />
                @else
                    <x-table.empty title="No supplier credits yet" text="Record goods you send back, or a credit note a vendor gives you. Use it against their next bill.">
                        @can('create bills')<a href="{{ route('vendor-credits.create') }}" class="btn-new">New supplier credit</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Supplier credits" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="vendor_credit_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Vendor</x-table.th>
                    <x-table.th>Bill</x-table.th>
                    <x-table.th field="credit_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th field="balance" :sort="[$sortField, $sortDirection]" num>Left to use</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($credits as $credit)
                    <tr wire:key="vc-{{ $credit->id }}">
                        <td>
                            <a href="{{ route('vendor-credits.show', $credit) }}" class="tbl-link">{{ $credit->vendor_credit_number }}</a>
                            @if ($credit->vendor_reference)<div class="text-xs tbl-muted">{{ $credit->vendor_reference }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $credit->vendor?->name ?? '—' }}</td>
                        <td>
                            @if ($credit->bill)
                                <a href="{{ route('bills.show', $credit->bill) }}" class="text-brand-700 hover:underline dark:text-brand-300">{{ $credit->bill->bill_number }}</a>
                            @else
                                <span class="tbl-zero">—</span>
                            @endif
                        </td>
                        <td class="tbl-muted">{{ $date($credit->credit_date) }}</td>
                        <td class="num">{{ $money($credit->total) }}</td>
                        <td class="num {{ $left($credit) ? '' : 'tbl-zero' }}">{{ $left($credit) ? $money($credit->balance) : '—' }}</td>
                        <td><x-status-badge :status="$st($credit)" :label="$label($credit)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$credit->vendor_credit_number">
                                <x-table.menu-item :href="route('vendor-credits.show', $credit)">{{ $left($credit) && $user->can('edit bills') ? 'View, use or refund' : 'View' }}</x-table.menu-item>
                                @if (in_array($st($credit), ['draft', 'void'], true) && $user->can('delete bills'))
                                    <x-table.menu-item wire="deleteOne({{ $credit->id }})" :confirm="'Delete '.$credit->vendor_credit_number.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'credit' : 'credits' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td class="num">{{ $money($totals->balance) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Supplier credits">
                @foreach ($credits as $credit)
                    <li wire:key="vc-card-{{ $credit->id }}">
                        <x-table.card :href="route('vendor-credits.show', $credit)" :title="$credit->vendor?->name ?? '—'" :amount="\App\Support\Money::format($credit->total)"
                            :meta="$credit->vendor_credit_number.' · '.$date($credit->credit_date)">
                            <x-slot name="badge"><x-status-badge :status="$st($credit)" :label="$label($credit)" /></x-slot>
                            @if ($left($credit))
                                <x-slot name="alert">{{ \App\Support\Money::format($credit->balance) }} left to use</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$credits" />
</div>
