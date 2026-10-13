{{-- E-invoices (tables plan T4): where each invoice or credit note stands with NRS. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $labels = \App\Livewire\EInvoices\EInvoicesTable::LABELS;
    $number = fn ($d) => $credit ? $d->credit_note_number : $d->invoice_number;
    $docDate = fn ($d) => ($credit ? $d->credit_note_date : $d->invoice_date)?->format('j M Y') ?? '—';
    $link = fn ($d) => $credit ? route('credit-notes.show', $d) : route('invoices.show', $d);
    $tone = ['not_submitted' => 'draft', 'pending' => 'pending', 'accepted' => 'accepted', 'rejected' => 'rejected', 'failed' => 'failed'];
    $sendable = $rows->filter(fn ($d) => ($d->eInvoice?->status ?? 'not_submitted') !== 'accepted');
    $ids = $sendable->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, customer or IRN" :selected="$canSubmit ? count($selectedItems) : 0" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="type" label="Show" :options="$types" />
            <x-table.select model="period" label="Date" :options="$periods" />
        </x-slot>
        @if ($canSubmit)
            <x-slot name="bulk">
                <button type="button" wire:click="submitSelected" wire:loading.attr="disabled" class="tbl-chip h-9">Send to NRS</button>
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($rows->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="Nothing matches these filters" />
                @else
                    <x-table.empty :title="$credit ? 'No credit notes yet' : 'No invoices yet'" text="Invoices show here once they are sent or issued." />
                @endif
            </div>
        @else
            <x-table :caption="$credit ? 'Credit notes and NRS' : 'Invoices and NRS'" class="hidden md:block">
                <x-slot name="head">
                    @if ($canSubmit)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every document not yet accepted on this page" />@endif
                    <x-table.th field="number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th>Customer</x-table.th>
                    <x-table.th field="date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Kind</x-table.th>
                    <x-table.th>NRS</x-table.th>
                    <x-table.th>IRN</x-table.th>
                </x-slot>
                @foreach ($rows as $doc)
                    @php $sub = $doc->eInvoice; $st = $sub?->status ?? 'not_submitted'; $ticked = in_array((string) $doc->id, $selectedItems, true); @endphp
                    <tr wire:key="ei-{{ $type }}-{{ $doc->id }}" @if ($ticked) data-picked @endif>
                        @if ($canSubmit)
                            @if ($st !== 'accepted')
                                <x-table.check :id="$doc->id" :label="$number($doc)" />
                            @else
                                <td class="tbl-check"></td>
                            @endif
                        @endif
                        <td><a href="{{ $link($doc) }}" class="tbl-link">{{ $number($doc) }}</a></td>
                        <td class="max-w-[16rem] truncate">{{ $doc->customer?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $docDate($doc) }}</td>
                        <td class="num">{{ $money($doc->total) }}</td>
                        <td class="tbl-muted">{{ strtoupper($kinds[$doc->id] ?? 'b2c') }}</td>
                        <td class="max-w-[18rem]">
                            <x-status-badge :status="$tone[$st] ?? 'draft'" :label="$labels[$st] ?? ucfirst($st)" />
                            @if (in_array($st, ['rejected', 'failed'], true) && $sub?->last_error)
                                <p class="mt-0.5 truncate text-xs text-red-700 dark:text-red-300" title="{{ $sub->last_error }}">{{ $sub->last_error }}</p>
                            @endif
                        </td>
                        <td class="max-w-[14rem] truncate font-mono text-xs {{ $st === 'accepted' ? '' : 'tbl-zero' }}">{{ $st === 'accepted' ? $sub->irn : '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Documents">
                @foreach ($rows as $doc)
                    @php $st = $doc->eInvoice?->status ?? 'not_submitted'; @endphp
                    <li wire:key="ei-card-{{ $type }}-{{ $doc->id }}">
                        <x-table.card :href="$link($doc)" :title="$doc->customer?->name ?? $number($doc)" :amount="\App\Support\Money::format($doc->total)" :meta="$number($doc).' · '.$docDate($doc)"
                            :tone="in_array($st, ['rejected', 'failed'], true) ? 'bad' : 'muted'">
                            <x-slot name="badge"><x-status-badge :status="$tone[$st] ?? 'draft'" :label="$labels[$st] ?? ucfirst($st)" /></x-slot>
                            @if (in_array($st, ['rejected', 'failed'], true) && $doc->eInvoice?->last_error)
                                <x-slot name="alert">{{ \Illuminate\Support\Str::limit($doc->eInvoice->last_error, 90) }}</x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$rows" />
</div>
