{{-- Money in and out of one bank account (tables plan T7): the postings to its ledger account, with the balance after each. Amounts in the business currency (the ledger). --}}
@php
    $fromText = \Carbon\Carbon::parse($from)->format('j M Y');
    $toText = \Carbon\Carbon::parse($to)->format('j M Y');
    $running = $pageOpening;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$bank->name.': money in and out'" :description="'Everything posted to this account from '.$fromText.' to '.$toText.', with the balance after each. Amounts in '.trim(\App\Support\Money::symbol()).'.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('banks.show', $bank)">The account</x-table.menu-item>
                @can('reconcile banks')<x-table.menu-item :href="route('banks.reconcile', $bank)">Reconcile</x-table.menu-item>@endcan
                @if ($bank->chart_of_account_id)
                    <x-table.menu-item :href="route('reports.general-ledger', ['account_id' => $bank->chart_of_account_id, 'start_date' => $from, 'end_date' => $to])">General ledger</x-table.menu-item>
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        <x-report.filters :action="route('banks.transactions', $bank)" button="Show">
            <x-report.date name="from" label="From" :value="$from" />
            <x-report.date name="to" label="To" :value="$to" />
        </x-report.filters>

        @unless ($linked)
            <x-report.check :ok="false">This bank account isn't linked to an account in the chart of accounts, so nothing posted to it can be shown. Edit the bank account to link it.</x-report.check>
        @endunless

        <x-report.stats :cols="4">
            <x-report.stat label="Balance at the start" :value="\App\Support\Figure::show($opening)" :hint="$fromText" />
            <x-report.stat label="Money in" :value="\App\Support\Figure::show($moneyIn)" tone="good" />
            <x-report.stat label="Money out" :value="\App\Support\Figure::show($moneyOut)" />
            <x-report.stat label="Balance at the end" :value="\App\Support\Figure::show($opening + $moneyIn - $moneyOut)" :hint="$toText" :tone="($opening + $moneyIn - $moneyOut) < 0 ? 'bad' : null" />
        </x-report.stats>

        @if ($entries->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No money in or out in this period" text="Payments, cash sales, transfers, payroll and journals that use this account show here." /></div>
        @else
            <x-table caption="Money in and out" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Date</x-table.th>
                    <x-table.th>What</x-table.th>
                    <x-table.th num>Money in</x-table.th>
                    <x-table.th num>Money out</x-table.th>
                    <x-table.th num>Balance</x-table.th>
                </x-slot>
                <tr class="rpt-section">
                    <td>{{ $fromText }}</td>
                    <td colspan="3">{{ $entries->currentPage() > 1 ? 'Brought forward from earlier pages' : 'Balance at the start' }}</td>
                    <td class="num">@fig($pageOpening)</td>
                </tr>
                @foreach ($entries as $entry)
                    @php $running += $entry->debit - $entry->credit; $src = \App\Support\JournalSource::of($entry->journal); @endphp
                    <tr>
                        <td class="tbl-muted">{{ $entry->journal->journal_date->format('j M Y') }}</td>
                        <td class="rpt-wrap max-w-[28rem]">
                            <a href="{{ $src['url'] }}" class="tbl-link">{{ $src['label'] }}</a>
                            <span class="block text-xs tbl-muted">{{ $entry->description ?: $entry->journal->description }}</span>
                        </td>
                        <td class="num {{ \App\Support\Figure::tone($entry->debit) }}">@fig($entry->debit)</td>
                        <td class="num {{ \App\Support\Figure::tone($entry->credit) }}">@fig($entry->credit)</td>
                        <td class="num">@fig($running)</td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="2">Total for the period · balance at the end</td>
                        <td class="num">@fig($moneyIn)</td>
                        <td class="num">@fig($moneyOut)</td>
                        <td class="num">@fig($opening + $moneyIn - $moneyOut)</td>
                    </tr>
                </x-slot>
            </x-table>
            @php $running = $pageOpening; @endphp
            <ul class="space-y-2 md:hidden" aria-label="Money in and out">
                @foreach ($entries as $entry)
                    @php $running += $entry->debit - $entry->credit; $src = \App\Support\JournalSource::of($entry->journal); @endphp
                    <li>
                        <x-table.card :href="$src['url']" :title="$src['label']" :amount="($entry->debit > 0 ? '+' : '-').number_format(max($entry->debit, $entry->credit), 2)"
                            :meta="$entry->journal->journal_date->format('j M Y').' · balance '.\App\Support\Figure::show($running)" :tone="$entry->credit > 0 ? 'bad' : 'muted'" />
                    </li>
                @endforeach
            </ul>
            <x-table.footer :rows="$entries" links />
        @endif
    </div>
</x-app-layout>
