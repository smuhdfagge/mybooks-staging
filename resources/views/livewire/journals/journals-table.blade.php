{{-- Journals list (tables plan T4). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $canBulk = $user->canAny(['post journals', 'edit journals', 'delete journals']);
    $ids = $journals->pluck('id')->map(fn ($id) => (string) $id)->all();
    $source = fn ($j) => $j->reference_type ? \Illuminate\Support\Str::of(class_basename($j->reference_type))->snake(' ')->ucfirst() : 'Manual';
    $label = ['pending' => 'Waiting'];
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, description, reference or amount" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="source" label="From" :options="$sources" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('post journals')<x-table.bulk-button action="post" confirm="Post the ticked draft journals to the books?">Post</x-table.bulk-button>@endcan
                @can('edit journals')<x-table.bulk-button action="void" confirm="Reverse the ticked manual journals? Each gets a reversing entry; journals made by documents are skipped.">Reverse</x-table.bulk-button>@endcan
                @can('delete journals')<x-table.bulk-button action="delete" danger confirm="Delete the ticked draft journals? Posted journals are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$journals" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($journals->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No journals match these filters" />
                @else
                    <x-table.empty title="No journals yet" text="Invoices, bills and payments make their own entries. Add a journal by hand for anything else.">
                        @can('create journals')<a href="{{ route('journals.create') }}" class="btn-new">New journal</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Journals" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every journal on this page" />@endif
                    <x-table.th field="journal_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th field="journal_date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th>Description</x-table.th>
                    <x-table.th>From</x-table.th>
                    <x-table.th field="total_debit" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($journals as $journal)
                    @php $ticked = in_array((string) $journal->id, $selectedItems, true); $manual = ! $journal->reference_type; @endphp
                    <tr wire:key="jr-{{ $journal->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$journal->id" :label="$journal->journal_number" />@endif
                        <td>
                            <a href="{{ route('journals.show', $journal) }}" class="tbl-link">{{ $journal->journal_number }}</a>
                            @php
                                // Accruals (S8): an automatic reversal, or one still to come.
                                $note = $journal->isAutoReversal() ? 'Automatic reversal'
                                    : ($journal->hasPendingReversal() ? 'Reverses '.$journal->reverse_on->format('j M') : null);
                            @endphp
                            @if ($journal->reference || $note)<div class="text-xs tbl-muted">{{ collect([$journal->reference, $note])->filter()->implode(' · ') }}</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $date($journal->journal_date) }}</td>
                        <td class="max-w-[22rem] truncate">{{ $journal->description ?: '—' }}</td>
                        <td class="tbl-muted">{{ $source($journal) }}</td>
                        <td class="num">{{ $money($journal->total_debit) }}</td>
                        <td><x-status-badge :status="$journal->status" :label="$label[$journal->status] ?? null" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$journal->journal_number">
                                <x-table.menu-item :href="route('journals.show', $journal)">View</x-table.menu-item>
                                @if ($journal->status === 'draft')
                                    @can('edit journals')<x-table.menu-item :href="route('journals.edit', $journal)">Edit</x-table.menu-item>@endcan
                                    @can('post journals')<x-table.menu-item :post="route('journals.post', $journal)" confirm="Post this journal to the books?">Post</x-table.menu-item>@endcan
                                    @can('delete journals')
                                        <x-table.menu-item wire="deleteOne({{ $journal->id }})" :confirm="'Delete '.$journal->journal_number.'?'" danger>Delete</x-table.menu-item>
                                    @endcan
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'journal' : 'journals' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->total) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Journals">
                @foreach ($journals as $journal)
                    <li wire:key="jr-card-{{ $journal->id }}">
                        <x-table.card :href="route('journals.show', $journal)" :title="$journal->description ?: $journal->journal_number" :amount="\App\Support\Money::format($journal->total_debit)"
                            :meta="$journal->journal_number.' · '.$date($journal->journal_date).' · '.$source($journal)">
                            @if ($journal->status !== 'posted')
                                <x-slot name="badge"><x-status-badge :status="$journal->status" :label="$label[$journal->status] ?? null" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$journals" />
</div>
