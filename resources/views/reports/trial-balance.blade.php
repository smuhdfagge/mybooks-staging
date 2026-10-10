{{-- Trial balance (tables plan T6): every account's debit or credit balance at a date. Amounts in ₦. --}}
@php
    $difference = round($totalDebits - $totalCredits, 2);
    $balanced = abs($difference) < 0.01;
    $asOfText = \Carbon\Carbon::parse($asOf)->format('j M Y');
    $types = \App\Models\ChartOfAccount::getTypes();
    $ledger = fn ($a) => route('reports.general-ledger', ['account_id' => $a->id, 'start_date' => \Carbon\Carbon::parse($asOf)->startOfMonth()->toDateString(), 'end_date' => $asOf]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Trial balance" :description="'Each account\'s balance at '.$asOfText.'. Debits should equal credits. Amounts in ₦.'"
            export="trial-balance" :filters="['as_of' => $asOf]">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.general-ledger')">General ledger</x-table.menu-item>
                <x-table.menu-item :href="route('reports.balance-sheet', ['as_of' => $asOf])">Balance sheet</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Trial balance" :period="'As at '.$asOfText">
        <x-report.filters :action="route('reports.trial-balance')">
            <x-report.date name="as_of" label="As at" :value="$asOf" />
        </x-report.filters>

        <x-report.check :ok="$balanced">
            @if ($balanced)
                Debits equal credits. The books balance.
            @else
                Debits and credits differ by {{ number_format(abs($difference), 2) }}. Check the journals posted up to {{ $asOfText }}.
            @endif
        </x-report.check>

        @if ($accounts->isEmpty())
            <div class="tbl-wrap"><x-table.empty title="No balances at this date" text="Nothing has been posted to any account up to this date." /></div>
        @else
            <x-table caption="Trial balance">
                <x-slot name="head">
                    <x-table.th>Account</x-table.th>
                    <x-table.th class="hidden sm:table-cell">Type</x-table.th>
                    <x-table.th num>Debit</x-table.th>
                    <x-table.th num>Credit</x-table.th>
                </x-slot>
                @foreach ($accounts as $account)
                    <tr>
                        <td class="rpt-wrap">
                            <span class="tbl-muted tabular-nums">{{ $account->account_code }}</span>
                            <a href="{{ $ledger($account) }}" class="tbl-link">{{ $account->name }}</a>
                        </td>
                        <td class="tbl-muted hidden sm:table-cell">{{ $types[$account->type] ?? ucfirst($account->type) }}</td>
                        <td class="num {{ \App\Support\Figure::tone($account->balance_debit) }}">@fig($account->balance_debit)</td>
                        <td class="num {{ \App\Support\Figure::tone($account->balance_credit) }}">@fig($account->balance_credit)</td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td>Total</td>
                        <td class="hidden sm:table-cell"></td>
                        <td class="num">@fig($totalDebits)</td>
                        <td class="num">@fig($totalCredits)</td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
