{{-- Bills list (tables plan T3): the Invoices list's design, for what you owe. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $st = fn ($b) => $b->status instanceof \BackedEnum ? $b->status->value : $b->status;
    $open = fn ($b) => in_array($st($b), ['unpaid', 'partial', 'overdue'], true) && $b->balance_due > 0;
    $late = fn ($b) => $open($b) && $b->due_date && $b->due_date->lt($today);
    $badge = fn ($b) => $late($b) ? 'overdue' : $st($b);
    $canBulk = $user->canAny(['edit bills', 'delete bills']);
    $ids = $bills->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, vendor's number, vendor or amount" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="vendor" label="Vendor" :options="$vendors" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit bills')<x-table.bulk-button action="mark_cancelled" confirm="Cancel the ticked bills? Bills with payments are skipped.">Cancel</x-table.bulk-button>@endcan
                @can('delete bills')<x-table.bulk-button action="delete" danger confirm="Delete the ticked bills? Bills with payments are skipped. This can't be undone.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$bills" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($bills->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No bills match these filters" />
                @else
                    <x-table.empty title="No bills yet" text="Record what vendors bill you, and see what you still have to pay.">
                        @can('create bills')<a href="{{ route('bills.create') }}" class="btn-new">New bill</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Bills" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every bill on this page" />@endif
                    <x-table.th field="bill_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Vendor</x-table.th>
                    <x-table.th field="bill_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="due_date" :sort="[$sortField, $sortDirection]">Due</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th field="balance_due" :sort="[$sortField, $sortDirection]" num>Still to pay</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($bills as $bill)
                    @php $ticked = in_array((string) $bill->id, $selectedItems, true); @endphp
                    <tr wire:key="bi-{{ $bill->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$bill->id" :label="$bill->bill_number" />@endif
                        <td>
                            <a href="{{ route('bills.show', $bill) }}" class="tbl-link">{{ $bill->bill_number }}</a>
                            @if ($bill->vendor_bill_number)<div class="text-xs tbl-muted">{{ $bill->vendor_bill_number }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $bill->vendor?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($bill->bill_date) }}</td>
                        <td class="{{ $late($bill) ? 'tbl-late' : 'tbl-muted' }}">{{ $date($bill->due_date) }}</td>
                        <td class="num">{{ $money($bill->total) }}</td>
                        <td class="num {{ $open($bill) ? '' : 'tbl-zero' }}">{{ $open($bill) ? $money($bill->balance_due) : '—' }}</td>
                        <td><x-status-badge :status="$badge($bill)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$bill->bill_number">
                                <x-table.menu-item :href="route('bills.show', $bill)">View</x-table.menu-item>
                                @if ($open($bill) && $user->can('create payments-made'))
                                    <x-table.menu-item :href="route('payments-made.create', ['bill_id' => $bill->id])">Record payment</x-table.menu-item>
                                @endif
                                @if ((float) $bill->amount_paid == 0 && $st($bill) !== 'cancelled' && $user->can('edit bills'))
                                    <x-table.menu-item :href="route('bills.edit', $bill)">Edit</x-table.menu-item>
                                @endif
                                @if ((float) $bill->amount_paid == 0 && $user->can('delete bills'))
                                    <x-table.menu-item wire="deleteOne({{ $bill->id }})" :confirm="'Delete '.$bill->bill_number.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'bill' : 'bills' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td class="num">{{ $money($totals->balance) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Bills">
                @foreach ($bills as $bill)
                    <li wire:key="bi-card-{{ $bill->id }}">
                        <x-table.card :href="route('bills.show', $bill)" :title="$bill->vendor?->name ?? '—'" :amount="\App\Support\Money::format($bill->total)"
                            :meta="$bill->bill_number.' · '.$date($bill->bill_date)" :tone="$late($bill) ? 'bad' : 'muted'">
                            <x-slot name="badge"><x-status-badge :status="$badge($bill)" /></x-slot>
                            @if ($late($bill))
                                <x-slot name="alert">Due {{ $date($bill->due_date) }} · {{ \App\Support\Money::format($bill->balance_due) }} to pay</x-slot>
                            @elseif ($open($bill) && $bill->amount_paid > 0)
                                <x-slot name="alert">{{ \App\Support\Money::format($bill->balance_due) }} still to pay</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">
                Total {{ \App\Support\Money::format($totals->total) }} · {{ \App\Support\Money::format($totals->balance) }} to pay
            </p>
        @endif
    </div>

    <x-table.footer :rows="$bills" />
</div>
