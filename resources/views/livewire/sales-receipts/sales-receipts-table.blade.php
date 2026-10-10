{{-- Sales receipts list (tables plan T2): cash sales, paid on the spot. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $method = fn ($m) => $m === 'pos' ? 'POS' : ($m ? ucfirst(str_replace('_', ' ', $m)) : '—');
    $methodOptions = collect($methods)->mapWithKeys(fn ($m) => [$m => $method($m)])->all();
    $canBulk = $user->can('delete sales-receipts');
    $ids = $receipts->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.toolbar placeholder="Search number, reference or customer" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="customer" label="Customer" :options="$customers" />
            <x-table.pick model="method" label="Paid by" :options="$methodOptions" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                <x-table.bulk-button action="delete" danger confirm="Delete the ticked receipts? This reverses their entries in the books.">Delete</x-table.bulk-button>
                <x-table.tick-all-matching :rows="$receipts" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($receipts->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered)
                    <x-table.empty filtered title="No sales receipts match these filters" />
                @else
                    <x-table.empty title="No sales receipts yet" text="Use a sales receipt when the customer pays on the spot.">
                        @can('create sales-receipts')<a href="{{ route('sales-receipts.create') }}" class="btn-new">New sales receipt</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Sales receipts" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every receipt on this page" />@endif
                    <x-table.th field="receipt_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Customer</x-table.th>
                    <x-table.th field="receipt_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th>Paid by</x-table.th>
                    <x-table.th>Reference</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($receipts as $receipt)
                    @php $ticked = in_array((string) $receipt->id, $selectedItems, true); @endphp
                    <tr wire:key="sr-{{ $receipt->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$receipt->id" :label="$receipt->receipt_number" />@endif
                        <td><a href="{{ route('sales-receipts.show', $receipt) }}" class="tbl-link">{{ $receipt->receipt_number }}</a></td>
                        <td class="max-w-[16rem] truncate">{{ $receipt->customer?->name ?? 'Walk-in customer' }}</td>
                        <td class="tbl-muted">{{ $date($receipt->receipt_date) }}</td>
                        <td>{{ $method($receipt->payment_method) }}</td>
                        <td class="max-w-[12rem] truncate {{ $receipt->reference ? 'tbl-muted' : 'tbl-zero' }}">{{ $receipt->reference ?: '—' }}</td>
                        <td class="num">{{ $money($receipt->total) }}</td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$receipt->receipt_number">
                                <x-table.menu-item :href="route('sales-receipts.show', $receipt)">View</x-table.menu-item>
                                <x-table.menu-item :href="route('sales-receipts.pdf', $receipt)">Download PDF</x-table.menu-item>
                                @can('edit sales-receipts')
                                    <x-table.menu-item :href="route('sales-receipts.edit', $receipt)">Edit</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="5">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'receipt' : 'receipts' }}@if ($filtered) <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Sales receipts">
                @foreach ($receipts as $receipt)
                    <li wire:key="sr-card-{{ $receipt->id }}">
                        <x-table.card :href="route('sales-receipts.show', $receipt)" :title="$receipt->customer?->name ?? 'Walk-in customer'" :amount="\App\Support\Money::format($receipt->total)"
                            :meta="$receipt->receipt_number.' · '.$date($receipt->receipt_date).' · '.$method($receipt->payment_method)" />
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Total {{ \App\Support\Money::format($totals->total) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$receipts" />
</div>
