<x-app-layout>
    {{-- VAT return, NRS Form 002 (tables plan T6: shared report pieces; filing forms unchanged). --}}
    @php
        $monthLabel = \Carbon\Carbon::createFromFormat('Y-m-d', $from)->format('F Y');
        $schedules = [
            ['Sales schedule', 'Customer', $salesSchedule],
            ['Sales adjustments (credit notes, refunds, cancellations)', 'Customer', $adjustmentsSchedule],
            ['Purchases schedule', 'Supplier', $purchasesSchedule],
        ];
    @endphp

    <x-slot name="header">
        <x-report.header :title="'VAT return · '.$monthLabel" description="Laid out like the NRS VAT Form 002, with the schedules to send. Amounts in ₦.">
            <x-slot name="downloads">
                <x-table.menu-item :href="route('reports.vat-return.export', ['month' => $month, 'format' => 'pdf'] + ($filing ? [] : array_filter($manual)))">Form 002 (PDF)</x-table.menu-item>
                <x-table.menu-item :href="route('reports.vat-return.export', ['month' => $month, 'format' => 'csv', 'schedule' => 'form'] + ($filing ? [] : array_filter($manual)))">Form 002 lines (CSV)</x-table.menu-item>
                <x-table.menu-item :href="route('reports.vat-return.export', ['month' => $month, 'format' => 'csv', 'schedule' => 'sales-upload'])">Sales schedule for NRS upload (CSV)</x-table.menu-item>
                <x-table.menu-item :href="route('reports.vat-return.export', ['month' => $month, 'format' => 'csv', 'schedule' => 'sales'])">Sales schedule, detailed (CSV)</x-table.menu-item>
                <x-table.menu-item :href="route('reports.vat-return.export', ['month' => $month, 'format' => 'csv', 'schedule' => 'adjustments'])">Sales adjustments (CSV)</x-table.menu-item>
                <x-table.menu-item :href="route('reports.vat-return.export', ['month' => $month, 'format' => 'csv', 'schedule' => 'purchases'])">Purchases schedule (CSV)</x-table.menu-item>
                <p class="mt-1 border-t border-gray-100 px-2.5 pt-2 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-400">NRS upload uses VAT status 0 VATable, 1 zero-rated, 2 exempt. Check it against the template from the NRS portal (Rev360) before uploading.</p>
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('reports.vat-gst-return')">VAT/GST return (any dates)</x-table.menu-item>
                <x-table.menu-item :href="route('reports.tax-liability')">Tax owed by period</x-table.menu-item>
            </x-slot>
        </x-report.header>
    </x-slot>

    <x-report.sheet :title="'VAT return · '.$monthLabel" :period="\Carbon\Carbon::parse($from)->format('j M Y').' to '.\Carbon\Carbon::parse($to)->format('j M Y')" class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <x-report.filters :action="route('reports.vat-return')" button="Show">
                <x-report.date name="month" label="Month" type="month" :value="$month" />
            </x-report.filters>
            <p class="max-w-xl text-xs text-gray-600 dark:text-gray-400">
                Figures come from the ledger for {{ $monthLabel }}: invoices and cash sales by invoice date, credit notes in the month issued.
                Small companies are exempt from monthly VAT returns under the Nigeria Tax Act 2025 but may file voluntarily.
            </p>
        </div>

        @if($drafts)
            <x-card class="p-4 border-l-4 border-amber-500" data-drafts>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Not on this return: {{ collect($drafts)->map(fn ($n, $label) => $n.' draft '.\Illuminate\Support\Str::plural($label, $n))->join(', ', ' and ') }}
                    dated in {{ $monthLabel }}. Drafts don't post to the books; approve or send them first if they belong in this month.
                </p>
            </x-card>
        @endif

        <!-- Filing (lines 65, 85, 90 by hand; settlement) -->
        @php($dueLabel = $dueDate->format('j F Y'))
        @if($filing)
            <x-card class="border-l-4 border-green-600 p-6" data-filed>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Filed</h3>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    Marked as filed on {{ $filing->filed_at->format('d/m/Y') }}{{ $filing->filedBy ? ' by '.$filing->filedBy->name : '' }}{{ $filing->reference ? ' (NRS reference '.$filing->reference.')' : '' }}.
                    @if((float) $filing->vat_payable > 0)
                        VAT payable (line 120): <strong>@money($filing->vat_payable)</strong>, due by {{ $dueLabel }}.
                    @else
                        No VAT to pay. Credit carried forward (line 115): <strong>@money($filing->credit_carried_forward)</strong>.
                    @endif
                </p>
                @if($filing->settlementJournal)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Output and input VAT were moved to VAT Payable by
                        <a href="{{ route('journals.show', $filing->settlementJournal) }}" class="underline">journal {{ $filing->settlementJournal->journal_number }}</a>.
                    </p>
                @endif
                @if($changedSinceFiling)
                    <p class="mt-3 text-sm text-amber-700 dark:text-amber-300" data-changed-since-filing>
                        Documents dated in this month have changed since it was filed: output VAT is now @money($lines[45]) (filed @money($filing->output_vat)),
                        input VAT @money($lines[75]) (filed @money($filing->input_vat)). The difference is not in the settlement; check with your accountant whether to amend the return.
                    </p>
                @endif
                @if(\App\Services\Accounting\LockDates::enabled())
                    @can('file vat-returns')
                        {{-- Reopen with a reason (session 11) --}}
                        <details class="mt-4" @if($errors->has('reason') || $errors->has('month')) open @endif>
                            <summary class="cursor-pointer text-sm font-medium text-brand-600 dark:text-brand-300">Reopen this return</summary>
                            <form method="POST" action="{{ route('reports.vat-return.reopen') }}" class="mt-3 space-y-3 max-w-xl">
                                @csrf
                                <input type="hidden" name="month" value="{{ $month }}">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    For an amended return. The settlement journal is reversed on the month's last day and the month can be filed again.
                                    The reason is kept in the lock date history.
                                </p>
                                <x-field name="reason" label="Reason" type="textarea" rows="2" required :value="old('reason')" />
                                @error('month')<p class="form-error">{{ $message }}</p>@enderror
                                <button type="submit" class="btn-primary" data-confirm="Reopen the VAT return for this month?">Reopen return</button>
                            </form>
                        </details>
                    @endcan
                @endif
            </x-card>
        @else
            <x-card class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">File this return</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Due by <strong>{{ $dueLabel }}</strong>, with any VAT payable. Enter the figures MyBooks can't take from your books, then press Update to see them on the form.
                </p>
                <form method="GET" action="{{ route('reports.vat-return') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <div><x-field name="imports" label="Line 65: imported goods (₦)" type="number" step="0.01" min="0" :value="$manual['imports'] ?: ''" /></div>
                    <div><x-field name="import_vat" label="VAT paid on those imports (₦)" type="number" step="0.01" min="0" :value="$manual['import_vat'] ?: ''" help="Already in line 75 if posted on a bill." /></div>
                    <div><x-field name="vat_withheld" label="Line 85: VAT deducted at source (₦)" type="number" step="0.01" min="0" :value="$manual['vat_withheld'] ?: ''" help="By government bodies or oil and gas companies." /></div>
                    <div><x-field name="auto_vat_paid" label="Line 90: automatic VAT paid (₦)" type="number" step="0.01" min="0" :value="$manual['auto_vat_paid'] ?: ''" /></div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <button type="submit" class="btn-secondary">Update</button>
                    </div>
                </form>
                @can('file vat-returns')
                    @if(\Carbon\Carbon::parse($to)->endOfDay()->isPast())
                        <form method="POST" action="{{ route('reports.vat-return.file') }}" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                              data-confirm="Mark the {{ $monthLabel }} VAT return as filed? Output and input VAT for the month move to VAT Payable and the month can't be filed again.">
                            @csrf
                            <input type="hidden" name="month" value="{{ $month }}">
                            @foreach($manual as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                When you have submitted the return to NRS, mark it as filed here. MyBooks moves the month's output and input VAT into VAT Payable
                                (one journal dated {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}) and keeps these figures; line 115 becomes next month's line 100.
                            </p>
                            <div class="max-w-sm mb-3">
                                <x-field name="reference" label="NRS acknowledgement or receipt number (optional)" maxlength="100" />
                            </div>
                            <button type="submit" class="btn-primary">Mark as filed and settle VAT</button>
                        </form>
                    @else
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">You can mark this return as filed once {{ $monthLabel }} has ended.</p>
                    @endif
                @endcan
            </x-card>
        @endif

        @if($unclassified->isNotEmpty())
            <x-card class="p-6 border-l-4 border-amber-500">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $unclassified->count() }} sales {{ Str::plural('line', $unclassified->count()) }} without VAT {{ $unclassified->count() === 1 ? 'is' : 'are' }} not classified</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    These lines charged no VAT and nothing says whether they are zero-rated, exempt or outside the scope of VAT, so they are counted in line 40 (sales subject to VAT) with no VAT.
                    Classify them before filing. To avoid this in future, give the item the "Zero-rated" or "Exempt" tax rate.
                </p>
                <form method="POST" action="{{ route('reports.vat-return.classify') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <x-table caption="Sales lines without VAT to classify">
                        <x-slot name="head">
                            <x-table.th>Date</x-table.th>
                            <x-table.th>Document</x-table.th>
                            <x-table.th>Customer</x-table.th>
                            <x-table.th>Line</x-table.th>
                            <x-table.th num>Amount</x-table.th>
                            <x-table.th>Treatment</x-table.th>
                        </x-slot>
                        @foreach ($unclassified as $i => $row)
                            <tr>
                                <td class="tbl-muted">{{ $row->date->format('d/m/Y') }}</td>
                                <td>{{ $row->document }} {{ $row->number }}</td>
                                <td class="rpt-wrap">{{ $row->party }}</td>
                                <td class="rpt-wrap">{{ $row->description }}</td>
                                <td class="num">@fig($row->net)</td>
                                <td>
                                    @if ($row->line_type)
                                        <input type="hidden" name="lines[{{ $i }}][type]" value="{{ $row->line_type }}">
                                        <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $row->line_id }}">
                                        <select name="lines[{{ $i }}][treatment]" aria-label="Treatment for {{ $row->description }}" class="h-8 rounded-md border-gray-300 py-0 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" @cannot('file vat-returns') disabled @endcannot>
                                            <option value="">Choose…</option>
                                            <option value="zero">Zero-rated</option>
                                            <option value="exempt">Exempt</option>
                                            <option value="out_of_scope">Outside the scope of VAT</option>
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                    @can('file vat-returns')
                        <button type="submit" class="btn-primary mt-4">Save classification</button>
                    @endcan
                </form>
            </x-card>
        @endif

        @if(abs($lines[45] - $expectedOutputVat) >= 1)
            <x-card class="p-4 border-l-4 border-amber-500">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Line 45 (@money($lines[45])) is not line 40 at {{ rtrim(rtrim(number_format($standardRate, 2), '0'), '.') }}% (@money($expectedOutputVat)).
                    That happens when sales without VAT are not classified, a line used another rate, or VAT was posted by journal. See the reconciliation below.
                </p>
            </x-card>
        @endif

        @if($exemptShare > 0 && $lines[75] > 0)
            <x-card class="p-4 border-l-4 border-brand-500">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ number_format($exemptShare * 100, 1) }}% of this month's sales are exempt. Input VAT that relates to exempt supplies can't be recovered,
                    so part of line 75 may need to be apportioned (on a straight split, @money(round($lines[75] * $exemptShare, 2))). Check with your accountant.
                </p>
            </x-card>
        @endif

        <!-- The form -->
        <section class="space-y-2" aria-labelledby="form-002">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 id="form-002" class="text-base font-semibold text-gray-900 dark:text-white">VAT Form 002 &middot; {{ $monthLabel }}</h3>
                <span class="text-sm text-gray-600 dark:text-gray-400">Period {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }} &middot; Currency NGN</span>
            </div>
            <x-table caption="VAT Form 002" class="max-w-4xl">
                <x-slot name="head">
                    <x-table.th>Line</x-table.th>
                    <x-table.th>What it is</x-table.th>
                    <x-table.th num>Amount</x-table.th>
                </x-slot>
                @foreach (\App\Services\Accounting\VatReturnForm::SECTIONS as $letter => [$title, $rows])
                    <tr class="rpt-section"><td colspan="3">Section {{ $letter }}: {{ $title }}</td></tr>
                    @foreach ($rows as $no => $label)
                        <tr @class(['rpt-sub' => in_array($no, [40, 45, 75, 95, 120])]) data-line="{{ $no }}">
                            <td class="tbl-muted tabular-nums">{{ $no }}</td>
                            <td class="rpt-wrap">{{ $label }}</td>
                            <td class="num">{{ number_format((float) $lines[$no], 2) }}</td>
                        </tr>
                        @if ($no === 75 && $importVat > 0)
                            <tr><td></td><td class="rpt-in1 text-xs tbl-muted">of which VAT on imports</td><td class="num text-xs tbl-muted">{{ number_format($importVat, 2) }}</td></tr>
                        @endif
                    @endforeach
                @endforeach
            </x-table>
            @if ($outOfScope != 0)
                <p class="text-xs text-gray-600 dark:text-gray-400">Sales outside the scope of VAT, not on the form: {{ number_format($outOfScope, 2) }}.</p>
            @endif
        </section>

        <!-- Reconciliation to the ledger -->
        @php($o = $reconciliation['output'])
        @php($n = $reconciliation['input'])
        <section class="space-y-2" aria-labelledby="vat-rec">
            <h3 id="vat-rec" class="text-base font-semibold text-gray-900 dark:text-white">Reconciliation to the ledger</h3>
            <x-table caption="Reconciliation to the ledger" class="max-w-4xl">
                <x-slot name="head">
                    <x-table.th><span class="sr-only">Item</span></x-table.th>
                    <x-table.th num>Output VAT ({{ $ledger['outputAccount'] }})</x-table.th>
                    <x-table.th num>Input VAT ({{ $ledger['inputAccount'] }})</x-table.th>
                </x-slot>
                <tr><td class="rpt-wrap">VAT on the document schedules</td><td class="num">@fig($o['documents'])</td><td class="num">@fig($n['documents'])</td></tr>
                <tr><td class="rpt-wrap">VAT posted without a document (journals)</td><td class="num {{ \App\Support\Figure::tone($o['other']) }}">@fig($o['other'])</td><td class="num {{ \App\Support\Figure::tone($n['other']) }}">@fig($n['other'])</td></tr>
                <tr><td class="rpt-wrap">Other differences</td><td class="num {{ \App\Support\Figure::tone($o['unexplained']) }}">@fig($o['unexplained'])</td><td class="num {{ \App\Support\Figure::tone($n['unexplained']) }}">@fig($n['unexplained'])</td></tr>
                <tr class="rpt-sub"><td class="rpt-wrap">Ledger movement for the month</td><td class="num">@fig($o['ledger'])</td><td class="num">@fig($n['ledger'])</td></tr>
                <tr class="rpt-sub"><td class="rpt-wrap">On the return (lines 45 and 75)</td><td class="num">@fig($o['return'])</td><td class="num">@fig($n['return'])</td></tr>
                <tr data-reconciliation-difference><td class="rpt-wrap">Difference, return vs ledger</td><td class="num {{ abs($o['difference']) >= 0.005 ? 'tbl-late' : 'tbl-zero' }}">@fig($o['difference'])</td><td class="num {{ abs($n['difference']) >= 0.005 ? 'tbl-late' : 'tbl-zero' }}">@fig($n['difference'])</td></tr>
            </x-table>
            @foreach (['Output' => $o['otherLines'], 'Input' => $n['otherLines']] as $side => $others)
                @if ($others->isNotEmpty())
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $side }} VAT posted without a document</p>
                    <ul class="ml-5 list-disc text-sm text-gray-600 dark:text-gray-400">
                        @foreach ($others as $other)
                            <li>{{ $other->date->format('d/m/Y') }} {{ $other->number }} {{ $other->description }}: {{ number_format($other->vat, 2) }}</li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        </section>

        <!-- Schedules -->
        @foreach ($schedules as [$title, $partyLabel, $rows])
            <section class="space-y-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                @if ($rows->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-gray-400">Nothing this month.</p>
                @else
                    <x-table :caption="$title">
                        <x-slot name="head">
                            <x-table.th>{{ $partyLabel }}</x-table.th>
                            <x-table.th class="hidden md:table-cell">TIN</x-table.th>
                            <x-table.th>Invoice no.</x-table.th>
                            <x-table.th>Date</x-table.th>
                            <x-table.th class="hidden lg:table-cell">Description</x-table.th>
                            <x-table.th class="hidden md:table-cell">VAT status</x-table.th>
                            <x-table.th num>Amount (excl. VAT)</x-table.th>
                            <x-table.th num>VAT</x-table.th>
                        </x-slot>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="rpt-wrap">{{ $row->party }}</td>
                                <td class="hidden md:table-cell tbl-muted">{{ $row->tin }}</td>
                                <td>{{ $row->number }} <span class="block text-xs tbl-muted">{{ $row->document }}</span></td>
                                <td class="tbl-muted">{{ $row->date->format('d/m/Y') }}</td>
                                <td class="rpt-wrap hidden lg:table-cell">{{ $row->description }}</td>
                                <td class="hidden md:table-cell {{ $row->treatment === null ? 'tbl-late' : '' }}">{{ $treatments[$row->treatment] ?? 'Not classified' }}</td>
                                <td class="num">{{ number_format($row->net, 2) }}</td>
                                <td class="num">{{ number_format($row->vat, 2) }}</td>
                            </tr>
                        @endforeach
                        <x-slot name="foot">
                            <tr>
                                <td>Total</td>
                                <td class="hidden md:table-cell"></td>
                                <td></td>
                                <td></td>
                                <td class="hidden lg:table-cell"></td>
                                <td class="hidden md:table-cell"></td>
                                <td class="num">{{ number_format($rows->sum('net'), 2) }}</td>
                                <td class="num">{{ number_format($rows->sum('vat'), 2) }}</td>
                            </tr>
                        </x-slot>
                    </x-table>
                @endif
            </section>
        @endforeach
    </x-report.sheet>
</x-app-layout>
