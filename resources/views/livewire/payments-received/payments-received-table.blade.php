{{-- Payments received list (tables plan T2): money in against invoices, and deposits. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $method = fn ($m) => match (true) { $m === 'deposit' => 'From a deposit', $m === 'pos' => 'POS', (bool) $m => ucfirst(str_replace('_', ' ', $m)), default => '—' };
    $methodOptions = collect($methods)->mapWithKeys(fn ($m) => [$m => $method($m)])->all();
    $canBulk = $user->can('delete payments-received');
    $ids = $payments->pluck('id')->map(fn ($id) => (string) $id)->all();
    $unused = fn ($p) => $p->is_deposit && $p->unused_amount > 0;
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, reference, customer or amount" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="customer" label="Customer" :options="$customers" />
            <x-table.pick model="method" label="Paid by" :options="$methodOptions" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                <x-table.bulk-button action="delete" danger confirm="Delete the ticked payments? The invoices they paid will show as owing again.">Delete</x-table.bulk-button>
                <x-table.tick-all-matching :rows="$payments" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($payments->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No payments match these filters" />
                @else
                    <x-table.empty title="No payments received yet" text="Record money a customer pays against an invoice, or a deposit paid in advance.">
                        @can('create payments-received')<a href="{{ route('payments-received.create') }}" class="btn-new">Record payment</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Payments received" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every payment on this page" />@endif
                    <x-table.th field="payment_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Customer</x-table.th>
                    <x-table.th field="payment_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th>For</x-table.th>
                    <x-table.th>Paid by</x-table.th>
                    <x-table.th field="amount" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th num>Not yet used</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($payments as $payment)
                    @php $ticked = in_array((string) $payment->id, $selectedItems, true); @endphp
                    <tr wire:key="pr-{{ $payment->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$payment->id" :label="$payment->payment_number" />@endif
                        <td>
                            <a href="{{ route('payments-received.show', $payment) }}" class="tbl-link">{{ $payment->payment_number }}</a>
                            @if ($payment->reference)<div class="text-xs tbl-muted">{{ $payment->reference }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $payment->customer?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($payment->payment_date) }}</td>
                        <td>
                            @if ($payment->invoice)
                                <a href="{{ route('invoices.show', $payment->invoice) }}" class="text-brand-700 hover:underline dark:text-brand-300">{{ $payment->invoice->invoice_number }}</a>
                            @elseif ($payment->is_deposit)
                                <x-status-badge status="open" label="Deposit" />
                            @else
                                <span class="tbl-zero">—</span>
                            @endif
                        </td>
                        <td>{{ $method($payment->payment_method) }}</td>
                        <td class="num">{{ $money($payment->amount) }}</td>
                        <td class="num {{ $unused($payment) ? '' : 'tbl-zero' }}">{{ $unused($payment) ? $money($payment->unused_amount) : '—' }}</td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$payment->payment_number">
                                <x-table.menu-item :href="route('payments-received.show', $payment)">View</x-table.menu-item>
                                @if ($unused($payment) && $user->can('edit payments-received'))
                                    <x-table.menu-item :href="route('payments-received.apply-deposit', $payment)">Apply to an invoice</x-table.menu-item>
                                @endif
                                @can('edit payments-received')
                                    <x-table.menu-item :href="route('payments-received.edit', $payment)">Edit</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="5">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'payment' : 'payments' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->amount) }}</td>
                        <td class="num">{{ $totals->unused > 0 ? $money($totals->unused) : '—' }}</td>
                        <td></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Payments received">
                @foreach ($payments as $payment)
                    <li wire:key="pr-card-{{ $payment->id }}">
                        <x-table.card :href="route('payments-received.show', $payment)" :title="$payment->customer?->name ?? '—'" :amount="\App\Support\Money::format($payment->amount)"
                            :meta="$payment->payment_number.' · '.$date($payment->payment_date).' · '.($payment->invoice?->invoice_number ?? ($payment->is_deposit ? 'Deposit' : $method($payment->payment_method)))">
                            @if ($unused($payment))
                                <x-slot name="alert">{{ \App\Support\Money::format($payment->unused_amount) }} not yet used</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Total {{ \App\Support\Money::format($totals->amount) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$payments" />
</div>
