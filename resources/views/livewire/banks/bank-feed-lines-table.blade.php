{{-- Bank lines to review (tables plan T4). Accept stays on the row: it is the job of this page. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $review = $tab === '';
    $ticks = $canReconcile && $review;
    $ids = $lines->pluck('id')->map(fn ($id) => (string) $id)->all();
    $btn = 'inline-flex h-8 items-center rounded-md px-2.5 text-[13px] font-semibold';
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search words or amount" :selected="$ticks ? count($selectedItems) : 0" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            @if (count($connections) > 1)<x-table.pick model="connection" label="Account" :options="$connections" />@endif
            <x-table.pick model="direction" label="Money" :options="$directions" all="In and out" />
        </x-slot>
        @if ($ticks)
            <x-slot name="end">
                <button type="button" data-testid="accept-all" wire:click="acceptAllSuggested"
                    wire:confirm="Accept every suggestion marked High? Each line will be matched to the record suggested. Nothing new is posted, and a match can be undone."
                    class="tbl-chip h-9">Accept all High suggestions</button>
            </x-slot>
            <x-slot name="bulk">
                <button type="button" wire:click="ignoreSelected" wire:loading.attr="disabled"
                    wire:confirm="Ignore the {{ count($selectedItems) }} ticked lines? You can bring them back from the Ignored tab."
                    class="tbl-chip h-9">Ignore</button>
                <x-table.tick-all-matching :rows="$lines" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($lines->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered)
                    <x-table.empty filtered title="No lines match these filters" />
                @elseif ($review)
                    <x-table.empty title="Nothing to review" text="New lines appear here when the bank sends them." />
                @else
                    <x-table.empty title="No lines here yet" />
                @endif
            </div>
        @else
            <x-table caption="Bank lines" class="hidden md:block">
                <x-slot name="head">
                    @if ($ticks)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every line on this page" />@endif
                    <x-table.th field="date" :sort="[$sortField, $sortDirection]">Date</x-table.th>
                    <x-table.th>What the bank says</x-table.th>
                    <x-table.th num>Money in</x-table.th>
                    <x-table.th num>Money out</x-table.th>
                    <x-table.th>In MyBooks</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($lines as $line)
                    @php $s = $suggestions[$line->id] ?? null; $ticked = in_array((string) $line->id, $selectedItems, true); @endphp
                    <tr wire:key="line-{{ $line->id }}" data-testid="line-{{ $line->id }}" @if ($ticked) data-picked @endif>
                        @if ($ticks)<x-table.check :id="$line->id" :label="$line->narration ?: 'line'" />@endif
                        <td class="tbl-muted">{{ $line->date->format('j M Y') }}</td>
                        <td class="max-w-[22rem]">
                            <span class="block truncate">{{ $line->narration ?: '—' }}</span>
                            @if (count($connections) > 1)<span class="block truncate text-xs tbl-muted">{{ $line->connection?->title() }}</span>@endif
                        </td>
                        <td class="num {{ $line->isCredit() ? '' : 'tbl-zero' }}">{{ $line->isCredit() ? $money($line->amount) : '—' }}</td>
                        <td class="num {{ $line->isCredit() ? 'tbl-zero' : '' }}">{{ $line->isCredit() ? '—' : $money($line->amount) }}</td>
                        <td class="max-w-[18rem]">
                            @if ($line->status === 'new')
                                @if ($s)
                                    <a class="block truncate text-brand-700 hover:underline dark:text-brand-300" href="{{ $s->candidate->url ?? '#' }}" target="_blank" rel="noopener">{{ $s->candidate->describe() }} · {{ $s->candidate->date->format('j M') }}</a>
                                    <span class="text-xs tbl-muted" data-testid="confidence-{{ $line->id }}">{{ $s->label() }} confidence</span>
                                @else
                                    <span class="tbl-muted">Nothing matches yet</span>
                                @endif
                            @elseif ($line->status === 'ignored')
                                <x-status-badge status="cancelled" label="Ignored" />
                            @else
                                <x-status-badge :status="$line->status === 'matched' ? 'accepted' : 'completed'" :label="$line->status === 'matched' ? 'Matched' : 'Recorded'" />
                                @if ($line->matched)<span class="ml-1 text-xs tbl-muted">{{ $line->matched->payment_number ?? $line->matched->expense_number ?? $line->matched->journal_number ?? '' }}</span>@endif
                            @endif
                        </td>
                        <td class="tbl-menu whitespace-nowrap">
                            @if ($canReconcile)
                                <div class="flex items-center justify-end gap-1">
                                    @if ($line->status === 'new' && $s)
                                        <button type="button" class="{{ $btn }} bg-brand-700 text-white hover:bg-brand-800" data-testid="accept-{{ $line->id }}"
                                            wire:click="accept({{ $line->id }}, @js($s->candidate->record::class), {{ $s->candidate->record->getKey() }})">Accept</button>
                                    @endif
                                    <x-table.dropdown :sr-label="'More for '.($line->narration ?: 'this line')">
                                        @if ($line->status === 'new')
                                            @if ($s)
                                                <x-table.menu-item wire="reject({{ $line->id }}, '{{ addslashes($s->candidate->record::class) }}', {{ $s->candidate->record->getKey() }})">Not this one</x-table.menu-item>
                                            @endif
                                            <x-table.menu-item :href="route('bank-feeds.lines.show', $line)">Record it</x-table.menu-item>
                                            <x-table.menu-item wire="ignore({{ $line->id }})">Ignore</x-table.menu-item>
                                        @elseif ($line->status === 'ignored')
                                            <x-table.menu-item wire="unignore({{ $line->id }})">Bring back</x-table.menu-item>
                                        @elseif ($line->status === 'matched')
                                            <x-table.menu-item wire="undo({{ $line->id }})" confirm="Take this match back?">Undo the match</x-table.menu-item>
                                        @else
                                            <x-table.menu-item :href="route('bank-feeds.lines.show', $line)">View</x-table.menu-item>
                                        @endif
                                    </x-table.dropdown>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($ticks)<td></td>@endif
                        <td colspan="2">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'line' : 'lines' }}@if ($filtered) <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->money_in) }}</td>
                        <td class="num">{{ $money($totals->money_out) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Bank lines">
                @foreach ($lines as $line)
                    @php $s = $suggestions[$line->id] ?? null; @endphp
                    <li wire:key="line-card-{{ $line->id }}">
                        <x-table.card :href="route('bank-feeds.lines.show', $line)" :title="$line->narration ?: '—'"
                            :amount="($line->isCredit() ? '+' : '−').\App\Support\Money::format($line->amount)"
                            :meta="$line->date->format('j M Y').($s ? ' · '.$s->label().' match' : '')" />
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$lines" />
</div>
