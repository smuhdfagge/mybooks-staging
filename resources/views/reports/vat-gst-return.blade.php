{{-- VAT summary (tables plan T6): VAT charged on sales less VAT paid on purchases, from the ledger, and settling it. Amounts in ₦. --}}
@php
    $from = \Carbon\Carbon::parse($startDate)->format('j M Y');
    $to = \Carbon\Carbon::parse($endDate)->format('j M Y');
    $rate = fn ($row) => $row->tax_rate === null ? 'No rate (expenses, journals)' : rtrim(rtrim(number_format($row->tax_rate, 2), '0'), '.').'%';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-report.header title="VAT/GST return" :description="'VAT charged on sales less VAT paid on purchases, '.$from.' to '.$to.', from the ledger. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.vat-return')">VAT return to file (Form 002)</x-table.menu-item>
                <x-table.menu-item :href="route('reports.tax-liability', ['start_date' => $startDate, 'end_date' => $endDate])">Tax owed by period</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet title="VAT/GST return" :period="$from.' to '.$to">
        <x-report.filters :action="route('reports.vat-gst-return')">
            <x-report.date name="start_date" label="From" :value="$startDate" />
            <x-report.date name="end_date" label="To" :value="$endDate" />
            <x-report.pick name="tax_rate_id" label="Tax rate" :value="$taxRateId" all="All rates"
                :options="$taxRates->mapWithKeys(fn ($r) => [$r->id => $r->name.' ('.$r->formatted_rate.')'])" />
        </x-report.filters>

        <x-report.stats :cols="3">
            <x-report.stat label="VAT on sales" :value="\App\Support\Figure::show($totalOutputTax)" :hint="'On '.number_format($totalOutputTaxable, 2).' of sales'" />
            <x-report.stat label="VAT on purchases" :value="\App\Support\Figure::show($totalInputTax)" :hint="'On '.number_format($totalInputTaxable, 2).' of purchases'" />
            <x-report.stat :label="$netTaxPayable >= 0 ? 'VAT to pay' : 'VAT to claim back'" :value="number_format(abs($netTaxPayable), 2)" :tone="$netTaxPayable > 0 ? 'bad' : null" hint="On sales less on purchases" />
        </x-report.stats>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ([['VAT on sales by rate', $outputTaxByRate, $totalOutputTaxable, $totalOutputTax, 'Sales'], ['VAT on purchases by rate', $inputTaxByRate, $totalInputTaxable, $totalInputTax, 'Purchases']] as [$caption, $rows, $base, $vat, $what])
                <section class="space-y-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $caption }}</h3>
                    <x-table :caption="$caption">
                        <x-slot name="head">
                            <x-table.th>Rate</x-table.th>
                            <x-table.th num>{{ $what }}</x-table.th>
                            <x-table.th num>VAT</x-table.th>
                            <x-table.th num class="hidden sm:table-cell">Entries</x-table.th>
                        </x-slot>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="rpt-wrap">{{ $rate($row) }}</td>
                                <td class="num">@fig($row->taxable_amount)</td>
                                <td class="num">@fig($row->tax_amount)</td>
                                <td class="num hidden sm:table-cell tbl-muted">{{ number_format($row->transaction_count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tbl-muted">None in this period.</td></tr>
                        @endforelse
                        @if ($rows->isNotEmpty())
                            <x-slot name="foot">
                                <tr>
                                    <td>Total</td>
                                    <td class="num">@fig($base)</td>
                                    <td class="num">@fig($vat)</td>
                                    <td class="num hidden sm:table-cell">{{ number_format($rows->sum('transaction_count')) }}</td>
                                </tr>
                            </x-slot>
                        @endif
                    </x-table>
                </section>
            @endforeach
        </div>

        @foreach ([['VAT on sales: each entry', 'Sales, cash sales, refunds and credit notes', $outputLines, $outputAccount], ['VAT on purchases: each entry', 'Bills and expenses', $inputLines, $inputAccount]] as [$caption, $hint, $lines, $account])
            <section class="space-y-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $caption }} <span class="text-sm font-normal tbl-muted">· {{ $hint }}</span></h3>
                <x-table :caption="$caption">
                    <x-slot name="head">
                        <x-table.th>Date</x-table.th>
                        <x-table.th>Document</x-table.th>
                        <x-table.th class="hidden sm:table-cell">With</x-table.th>
                        <x-table.th num>VAT</x-table.th>
                    </x-slot>
                    @forelse ($lines->take(50) as $line)
                        <tr>
                            <td class="tbl-muted">{{ $line->date->format('j M Y') }}</td>
                            <td><a href="{{ route('journals.show', $line->journal_id) }}" class="tbl-link">{{ $line->type }} {{ $line->number }}</a></td>
                            <td class="rpt-wrap hidden sm:table-cell {{ $line->party ? '' : 'tbl-zero' }}">{{ $line->party ?? '—' }}</td>
                            <td class="num">@fig($line->vat)</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="tbl-muted">None in this period.</td></tr>
                    @endforelse
                </x-table>
                @if ($lines->count() > 50)
                    <p class="text-sm text-gray-600 dark:text-gray-400">Showing 50 of {{ number_format($lines->count()) }}. Account {{ $account }} in the general ledger has them all.</p>
                @endif
            </section>
        @endforeach

        {{-- VAT settlement (A5) --}}
        <section class="no-print space-y-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800" aria-labelledby="settle-title">
            <h3 id="settle-title" class="text-base font-semibold text-gray-900 dark:text-white">Settle this return</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Figures come from the ledger: VAT on sales on account {{ $outputAccount }}, VAT on purchases on account {{ $inputAccount }}.
                When you file the return, settle it: the period's VAT moves into VAT Payable, ready for the payment to the tax office.
            </p>
            @if ($settlement)
                <x-report.check>Settled on {{ $settlement->journal_date->format('j M Y') }} (<a href="{{ route('journals.show', $settlement) }}" class="tbl-link">journal {{ $settlement->journal_number }}</a>).</x-report.check>
            @else
                @can('create journals')
                    <form method="POST" action="{{ route('reports.vat-gst-return.settle') }}" data-confirm="Settle VAT for {{ $from }} to {{ $to }}? Net {{ number_format($netTaxPayable, 2) }} goes to VAT Payable.">
                        @csrf
                        <input type="hidden" name="start_date" value="{{ $startDate }}">
                        <input type="hidden" name="end_date" value="{{ $endDate }}">
                        <button type="submit" class="btn-primary">Settle VAT for this period</button>
                    </form>
                @endcan
            @endif
        </section>
    </x-report.sheet>
</x-app-layout>
