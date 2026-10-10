{{-- Invoices list (tables plan T1): the pilot for every list in MyBooks. --}}
@php
    $user = auth()->user();
    $pageIds = $invoices->pluck('id')->map(fn ($id) => (string) $id)->all();
    $pageTicked = $pageIds && ! array_diff($pageIds, $selectedItems);
    $someTicked = (bool) array_intersect($pageIds, $selectedItems);
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $open = fn ($inv) => in_array($inv->status, ['sent', 'unpaid', 'partial', 'overdue'], true) && $inv->balance_due > 0;
    $late = fn ($inv) => $open($inv) && $inv->due_date && $inv->due_date->lt($today);
    $badge = fn ($inv) => $late($inv) ? 'overdue' : $inv->status;
    $canBulk = $user->canAny(['send invoices', 'edit invoices', 'delete invoices']);
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" model="tab" />

    <x-table.toolbar placeholder="Search number, customer or amount" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <label class="inline-flex items-center">
                <span class="sr-only">Customer</span>
                <select wire:model.live="customer" class="h-9 max-w-[14rem] rounded-md border-gray-300 py-0 pl-2.5 pr-8 text-[13px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">Customer: All</option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('send invoices')<x-table.bulk-button action="mark_sent" confirm="Mark the ticked draft invoices as sent? This posts them to the books.">Mark as sent</x-table.bulk-button>@endcan
                @can('edit invoices')<x-table.bulk-button action="mark_cancelled" confirm="Cancel the ticked invoices? Invoices with payments are skipped.">Cancel</x-table.bulk-button>@endcan
                @can('delete invoices')<x-table.bulk-button action="delete" danger confirm="Delete the ticked invoices? This can't be undone.">Delete</x-table.bulk-button>@endcan
                @if ($pageTicked && $invoices->total() > count($selectedItems))
                    <button type="button" wire:click="selectAllMatching" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                        Tick all {{ number_format(min($invoices->total(), 1000)) }}{{ $invoices->total() > 1000 ? ' (the first 1,000)' : '' }}
                    </button>
                @endif
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        {{-- Loading: a light veil over the rows while Livewire fetches. --}}
        <div wire:loading.delay.class.remove="hidden" class="hidden absolute inset-0 z-10 rounded-lg bg-white/50 dark:bg-gray-900/40" aria-hidden="true"></div>

        @if ($invoices->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No invoices match these filters" />
                @else
                    <x-table.empty title="No invoices yet" text="Invoices you create appear here, with what each customer still owes.">
                        @can('create invoices')<a href="{{ route('invoices.create') }}" class="btn-new">New invoice</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            {{-- Computer: the table --}}
            <x-table caption="Invoices" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)
                        <th scope="col" class="tbl-check">
                            <input type="checkbox" class="tbl-checkbox" aria-label="Tick every invoice on this page"
                                @checked($pageTicked) x-init="$el.indeterminate = {{ $someTicked && ! $pageTicked ? 'true' : 'false' }}"
                                wire:click="selectPage({{ json_encode($pageIds) }}, $event.target.checked)">
                        </th>
                    @endif
                    <x-table.th field="invoice_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Customer</x-table.th>
                    <x-table.th field="invoice_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="due_date" :sort="[$sortField, $sortDirection]">Due</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th field="balance_due" :sort="[$sortField, $sortDirection]" num>Balance due</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>

                @foreach ($invoices as $invoice)
                    @php $ticked = in_array((string) $invoice->id, $selectedItems, true); @endphp
                    <tr wire:key="inv-{{ $invoice->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)
                            <td class="tbl-check">
                                <input type="checkbox" class="tbl-checkbox" wire:model.live="selectedItems" value="{{ $invoice->id }}" aria-label="Tick {{ $invoice->invoice_number }}">
                            </td>
                        @endif
                        <td><a href="{{ route('invoices.show', $invoice) }}" class="tbl-link">{{ $invoice->invoice_number }}</a></td>
                        <td class="max-w-[16rem] truncate">{{ $invoice->customer?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($invoice->invoice_date) }}</td>
                        <td class="{{ $late($invoice) ? 'tbl-late' : 'tbl-muted' }}">{{ $date($invoice->due_date) }}</td>
                        <td class="num">{{ $money($invoice->total) }}</td>
                        <td class="num {{ $invoice->balance_due > 0 ? '' : 'tbl-zero' }}">{{ $invoice->balance_due > 0 ? $money($invoice->balance_due) : '—' }}</td>
                        <td><x-status-badge :status="$badge($invoice)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$invoice->invoice_number">
                                <x-table.menu-item :href="route('invoices.show', $invoice)">View</x-table.menu-item>
                                <x-table.menu-item :href="route('invoices.print', $invoice)" new-tab>Print</x-table.menu-item>
                                @if ($invoice->status === 'draft' && $user->can('send invoices'))
                                    <x-table.menu-item :post="route('invoices.send', $invoice)">Send</x-table.menu-item>
                                @endif
                                @if ($open($invoice) && $user->can('create payments-received'))
                                    <x-table.menu-item :href="route('payments-received.create', ['invoice_id' => $invoice->id])">Record payment</x-table.menu-item>
                                @endif
                                @if ($invoice->status !== 'paid' && $user->can('edit invoices'))
                                    <x-table.menu-item :href="route('invoices.edit', $invoice)">Edit</x-table.menu-item>
                                @endif
                                @if ($invoice->status === 'paid' && ! $invoice->released_at && $user->can('edit invoices'))
                                    <x-table.menu-item :post="route('invoices.release', $invoice)" confirm="Release this invoice and take the items out of stock?">Release and deduct stock</x-table.menu-item>
                                @endif
                                @if ($invoice->released_at)
                                    <x-table.menu-item :href="route('invoices.waybill', $invoice)" new-tab>Waybill</x-table.menu-item>
                                @endif
                                @can('delete invoices')
                                    <x-table.menu-item wire="deleteOne({{ $invoice->id }})" :confirm="'Delete '.$invoice->invoice_number.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach

                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'invoice' : 'invoices' }}@if ($filtered || $tab !== '') <span class="font-normal text-gray-600 dark:text-gray-400">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td class="num">{{ $money($totals->balance) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>

            {{-- Phone: one card per invoice --}}
            <ul class="space-y-2 md:hidden" aria-label="Invoices">
                @foreach ($invoices as $invoice)
                    @php
                        $part = $invoice->status === 'partial' || ($open($invoice) && $invoice->amount_paid > 0);
                    @endphp
                    <li wire:key="inv-card-{{ $invoice->id }}">
                        <x-table.card :href="route('invoices.show', $invoice)" :title="$invoice->customer?->name ?? '—'"
                            :amount="\App\Support\Money::format($invoice->total)"
                            :meta="$invoice->invoice_number.' · '.($invoice->status === 'draft' ? 'Not sent' : $date($invoice->invoice_date))"
                            :tone="$late($invoice) ? 'bad' : 'muted'">
                            <x-slot name="badge"><x-status-badge :status="$badge($invoice)" /></x-slot>
                            @if ($late($invoice))
                                <x-slot name="alert">Due {{ $date($invoice->due_date) }} · {{ \App\Support\Money::format($invoice->balance_due) }} to pay</x-slot>
                            @elseif ($part)
                                <x-slot name="alert">{{ \App\Support\Money::format($invoice->balance_due) }} still to pay</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">
                Total {{ \App\Support\Money::format($totals->total) }} · {{ \App\Support\Money::format($totals->balance) }} to collect
            </p>
        @endif
    </div>

    <x-table.footer :rows="$invoices" />
</div>
