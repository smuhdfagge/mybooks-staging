{{--
    Receivables & payables check (session 10, tables plan T6): ledger control
    accounts against the customer and supplier statement balances.
--}}
@php $asOfText = \Carbon\Carbon::parse($asOf)->format('j M Y'); @endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="Receivables & payables check" description="Does the ledger agree with what customers owe you and what you owe suppliers? Amounts in ₦.">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.accounts-receivable', ['as_of' => $asOf])">Aged receivables</x-table.menu-item>
                <x-table.menu-item :href="route('reports.accounts-payable', ['as_of' => $asOf])">Aged payables</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="Receivables & payables check" :period="'As at '.$asOfText" class="space-y-6">
        <x-report.filters :action="route('reports.control-reconciliation')" button="Check">
            <x-report.date name="as_of" label="As at" :value="$asOf" />
        </x-report.filters>

        @foreach ($sections as $key => $sec)
            @php
                $isAr = $key === 'receivables';
                $ok = abs($sec['difference']) < 0.005;
                $problems = collect($sec['items'])->where('expected', false);
                $expected = collect($sec['items'])->where('expected', true);
                [$badge, $label] = $ok ? ['completed', 'Agrees'] : (($problems->isEmpty() && abs($sec['unexplained']) < 0.005) ? ['confirmed', 'Difference explained'] : ['pending', 'Needs a look']);
            @endphp
            <section class="space-y-3" aria-labelledby="sec-{{ $key }}">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 id="sec-{{ $key }}" class="text-base font-semibold text-gray-900 dark:text-white">{{ $sec['title'] }}</h3>
                    <span class="text-sm tbl-muted">{{ $sec['account'] ? $sec['account']->account_code.' '.$sec['account']->name : 'account '.$sec['code'].' not found' }}</span>
                    <x-status-badge :status="$badge" :label="$label" />
                </div>

                <x-report.stats :cols="3">
                    <x-report.stat label="Balance in the ledger" :value="\App\Support\Figure::show($sec['ledger'])" />
                    <x-report.stat :label="$isAr ? 'Total of all customer balances' : 'Total of all supplier balances'" :value="\App\Support\Figure::show($sec['statements'])" />
                    <x-report.stat label="Difference" :value="number_format($sec['difference'], 2)" :tone="$ok ? 'good' : 'bad'" />
                </x-report.stats>

                @unless ($ok)
                    @foreach ([['Needs a look', $problems], ['Expected (no action needed)', $expected]] as [$heading, $list])
                        @if ($list->isNotEmpty())
                            <x-table :caption="$heading">
                                <x-slot name="head">
                                    <x-table.th>{{ $heading }}</x-table.th>
                                    <x-table.th class="hidden sm:table-cell">Date</x-table.th>
                                    <x-table.th num>Adds to the difference</x-table.th>
                                </x-slot>
                                @foreach ($list as $item)
                                    <tr>
                                        <td class="rpt-wrap">
                                            @if ($item['url'])
                                                <a href="{{ $item['url'] }}" class="tbl-link">{{ $item['label'] }}</a>
                                            @else
                                                <span class="font-medium">{{ $item['label'] }}</span>
                                            @endif
                                            <div class="text-xs tbl-muted">{{ $item['detail'] }}</div>
                                        </td>
                                        <td class="tbl-muted hidden sm:table-cell">{{ $item['date'] ? \Carbon\Carbon::parse($item['date'])->format('j M Y') : '—' }}</td>
                                        <td class="num">{{ number_format($item['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </x-table>
                        @endif
                    @endforeach
                    @if (abs($sec['unexplained']) >= 0.005)
                        <x-report.check :ok="false">{{ number_format($sec['unexplained'], 2) }} of the difference isn't explained by the items above.</x-report.check>
                    @endif
                    <p class="text-xs text-gray-600 dark:text-gray-400">Amounts are what each item adds to the difference (ledger minus {{ $isAr ? 'customer' : 'supplier' }} balances).</p>
                @endunless
            </section>
        @endforeach

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Each invoice, payment, credit note and refund (bill, payment, supplier credit, advance and asset bought on account for suppliers) is matched with its own journal.
            Journals posted straight to Accounts Receivable or Accounts Payable don't show on any customer or supplier statement, so they are listed here.
        </p>
    </x-report.sheet>
</x-app-layout>
