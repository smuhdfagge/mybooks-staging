{{-- General ledger (tables plan T6): one account's postings with a running balance. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $drcr = fn ($v) => abs(round((float) $v, 2)) < 0.005 ? '—' : number_format(abs($v), 2).' '.($v >= 0 ? 'Dr' : 'Cr');
    $types = \App\Models\ChartOfAccount::getTypes();
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="General ledger" :description="'Every posting to one account from '.$from.' to '.$to.', with the balance after each. Amounts in ₦.'"
            export="general-ledger" :filters="['start_date' => $startDate, 'end_date' => $endDate, 'account_id' => $accountId]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.trial-balance', ['as_of' => $endDate])">Trial balance</x-table.menu-item>
                @can('view journals')<x-table.menu-item :href="route('journals.index')">Journals</x-table.menu-item>@endcan
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="General ledger" :period="($selectedAccount ? $selectedAccount->account_code.' '.$selectedAccount->name.' · ' : '').$from.' to '.$to">
        <x-report.filters :action="route('reports.general-ledger')">
            <x-report.pick name="account_id" label="Account" :value="$accountId" all="Choose an account" required
                :options="$accounts->mapWithKeys(fn ($a) => [$a->id => $a->account_code.' '.$a->name])" />
            <x-report.date name="start_date" label="From" :value="$startDate" />
            <x-report.date name="end_date" label="To" :value="$endDate" />
        </x-report.filters>

        @if (! $selectedAccount)
            <div class="tbl-wrap"><x-table.empty title="Choose an account" text="Pick an account above to see every posting to it and its balance after each one." /></div>
        @else
            <x-report.stats :cols="4">
                <x-report.stat label="Opening balance" :value="$drcr($openingBalance)" :hint="'At '.$from" />
                <x-report.stat label="Debits" :value="\App\Support\Figure::show($totalDebit)" />
                <x-report.stat label="Credits" :value="\App\Support\Figure::show($totalCredit)" />
                <x-report.stat label="Closing balance" :value="$drcr($closingBalance)" :hint="'At '.$to" />
            </x-report.stats>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedAccount->account_code }} {{ $selectedAccount->name }}</span>
                · {{ $types[$selectedAccount->type] ?? ucfirst($selectedAccount->type) }}
            </p>

            @php $running = $pageOpeningBalance; @endphp
            <x-table caption="Ledger postings" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Date</x-table.th>
                    <x-table.th>Journal</x-table.th>
                    <x-table.th>Description</x-table.th>
                    <x-table.th num>Debit</x-table.th>
                    <x-table.th num>Credit</x-table.th>
                    <x-table.th num>Balance</x-table.th>
                </x-slot>
                <tr class="rpt-section">
                    <td>{{ $from }}</td>
                    <td colspan="4">{{ $entries->currentPage() > 1 ? 'Brought forward from earlier pages' : 'Opening balance' }}</td>
                    <td class="num">{{ $drcr($pageOpeningBalance) }}</td>
                </tr>
                @forelse ($entries as $entry)
                    @php $running += ($entry->debit - $entry->credit); @endphp
                    <tr>
                        <td class="tbl-muted">{{ \Carbon\Carbon::parse($entry->journal->journal_date)->format('j M Y') }}</td>
                        <td><a href="{{ route('journals.show', $entry->journal) }}" class="tbl-link">{{ $entry->journal->journal_number }}</a></td>
                        <td class="rpt-wrap max-w-[28rem]">{{ $entry->description ?: ($entry->journal->description ?: '—') }}</td>
                        <td class="num {{ \App\Support\Figure::tone($entry->debit) }}">@fig($entry->debit)</td>
                        <td class="num {{ \App\Support\Figure::tone($entry->credit) }}">@fig($entry->credit)</td>
                        <td class="num">{{ $drcr($running) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tbl-muted">Nothing was posted to this account in this period.</td></tr>
                @endforelse
                <x-slot name="foot">
                    <tr>
                        <td colspan="3">Total for the period · closing balance</td>
                        <td class="num">@fig($totalDebit)</td>
                        <td class="num">@fig($totalCredit)</td>
                        <td class="num">{{ $drcr($closingBalance) }}</td>
                    </tr>
                </x-slot>
            </x-table>

            @php $running = $pageOpeningBalance; @endphp
            <ul class="space-y-2 md:hidden" aria-label="Ledger postings">
                @foreach ($entries as $entry)
                    @php $running += ($entry->debit - $entry->credit); @endphp
                    <li>
                        <x-table.card :href="route('journals.show', $entry->journal)" :title="$entry->description ?: ($entry->journal->description ?: $entry->journal->journal_number)"
                            :amount="number_format(max($entry->debit, $entry->credit), 2).($entry->debit > 0 ? ' Dr' : ' Cr')"
                            :meta="\Carbon\Carbon::parse($entry->journal->journal_date)->format('j M Y').' · '.$entry->journal->journal_number.' · balance '.$drcr($running)" />
                    </li>
                @endforeach
            </ul>

            <x-table.footer :rows="$entries" links />
        @endif
    </x-report.sheet>
</x-app-layout>
