{{-- Customer credit notes list (tables plan T2). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $st = fn ($n) => $n->status instanceof \BackedEnum ? $n->status->value : $n->status;
    $label = fn ($n) => $st($n) === 'closed' ? 'Used up' : null;
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, invoice or customer" :filtered="$filtered">
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
                    <x-table.empty filtered title="No credit notes match these filters" />
                @else
                    <x-table.empty title="No credit notes yet" text="Use one when a customer returns goods or you agree a lower price.">
                        @can('create invoices')<a href="{{ route('credit-notes.create') }}" class="btn-new">New credit note</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Credit notes" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="credit_note_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Customer</x-table.th>
                    <x-table.th>Invoice</x-table.th>
                    <x-table.th field="credit_note_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th field="balance" :sort="[$sortField, $sortDirection]" num>Left to use</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($notes as $note)
                    <tr wire:key="cn-{{ $note->id }}">
                        <td><a href="{{ route('credit-notes.show', $note) }}" class="tbl-link">{{ $note->credit_note_number }}</a></td>
                        <td class="max-w-[16rem] truncate">{{ $note->customer?->name ?? '—' }}</td>
                        <td>
                            @if ($note->invoice)
                                <a href="{{ route('invoices.show', $note->invoice) }}" class="text-brand-700 hover:underline dark:text-brand-300">{{ $note->invoice->invoice_number }}</a>
                            @else
                                <span class="tbl-zero">—</span>
                            @endif
                        </td>
                        <td class="tbl-muted">{{ $date($note->credit_note_date) }}</td>
                        <td class="num">{{ $money($note->total) }}</td>
                        <td class="num {{ $note->balance > 0 && $st($note) === 'open' ? '' : 'tbl-zero' }}">{{ $note->balance > 0 && $st($note) === 'open' ? $money($note->balance) : '—' }}</td>
                        <td><x-status-badge :status="$st($note)" :label="$label($note)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$note->credit_note_number">
                                <x-table.menu-item :href="route('credit-notes.show', $note)">View</x-table.menu-item>
                                <x-table.menu-item :href="route('credit-notes.print', $note)" new-tab>Print</x-table.menu-item>
                                <x-table.menu-item :href="route('credit-notes.pdf', $note)">Download PDF</x-table.menu-item>
                                @if ($st($note) === 'open' && $note->balance > 0 && $user->can('edit invoices'))
                                    <x-table.menu-item :href="route('credit-notes.apply', $note)">Apply to an invoice</x-table.menu-item>
                                @endif
                                @if ($st($note) === 'draft' && $user->can('edit invoices'))
                                    <x-table.menu-item :href="route('credit-notes.edit', $note)">Edit</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'credit note' : 'credit notes' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td class="num">{{ $money($totals->balance) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Credit notes">
                @foreach ($notes as $note)
                    <li wire:key="cn-card-{{ $note->id }}">
                        <x-table.card :href="route('credit-notes.show', $note)" :title="$note->customer?->name ?? '—'" :amount="\App\Support\Money::format($note->total)"
                            :meta="$note->credit_note_number.' · '.$date($note->credit_note_date)">
                            <x-slot name="badge"><x-status-badge :status="$st($note)" :label="$label($note)" /></x-slot>
                            @if ($st($note) === 'open' && $note->balance > 0)
                                <x-slot name="alert">{{ \App\Support\Money::format($note->balance) }} left to use</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$notes" />
</div>
