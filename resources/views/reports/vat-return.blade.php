<x-app-layout>
    @php
        $monthLabel = \Carbon\Carbon::createFromFormat('Y-m-d', $from)->format('F Y');
        $th = 'px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase';
        $td = 'px-4 py-2 text-sm text-gray-900 dark:text-gray-100';
        $schedules = [
            ['Sales schedule', 'Customer', $salesSchedule],
            ['Sales adjustments (credit notes, refunds, cancellations)', 'Customer', $adjustmentsSchedule],
            ['Purchases schedule', 'Supplier', $purchasesSchedule],
        ];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('VAT return') }} &middot; {{ $monthLabel }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                {{-- vat-return:actions --}}
                <a href="{{ route('reports.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Back to Reports</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-card class="p-6">
            <form method="GET" action="{{ route('reports.vat-return') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Month</label>
                    <input type="month" name="month" id="month" value="{{ $month }}"
                        class="mt-1 block rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">Show</button>
                <p class="text-xs text-gray-500 dark:text-gray-400 sm:ml-auto max-w-xl">
                    Laid out like the Nigeria Revenue Service VAT Form 002. Figures come from the ledger for {{ $monthLabel }}:
                    invoices and cash sales by invoice date, credit notes in the month issued. Small companies are exempt from monthly
                    VAT returns under the Nigeria Tax Act 2025 but may file voluntarily.
                </p>
            </form>
        </x-card>

        {{-- vat-return:filing --}}

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
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="{{ $th }} text-left">Date</th>
                                    <th class="{{ $th }} text-left">Document</th>
                                    <th class="{{ $th }} text-left">Customer</th>
                                    <th class="{{ $th }} text-left">Line</th>
                                    <th class="{{ $th }} text-right">Amount</th>
                                    <th class="{{ $th }} text-left">Treatment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($unclassified as $i => $row)
                                    <tr>
                                        <td class="{{ $td }}">{{ $row->date->format('d/m/Y') }}</td>
                                        <td class="{{ $td }}">{{ $row->document }} {{ $row->number }}</td>
                                        <td class="{{ $td }}">{{ $row->party }}</td>
                                        <td class="{{ $td }}">{{ $row->description }}</td>
                                        <td class="{{ $td }} text-right">@money($row->net)</td>
                                        <td class="{{ $td }}">
                                            @if($row->line_type)
                                                <input type="hidden" name="lines[{{ $i }}][type]" value="{{ $row->line_type }}">
                                                <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $row->line_id }}">
                                                <select name="lines[{{ $i }}][treatment]" aria-label="Treatment for {{ $row->description }}" class="rounded-md border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" @cannot('file vat-returns') disabled @endcannot>
                                                    <option value="">Choose…</option>
                                                    <option value="zero">Zero-rated</option>
                                                    <option value="exempt">Exempt</option>
                                                    <option value="out_of_scope">Outside the scope of VAT</option>
                                                </select>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @can('file vat-returns')
                        <button type="submit" class="mt-4 inline-flex items-center px-4 py-2 bg-amber-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700">Save classification</button>
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
            <x-card class="p-4 border-l-4 border-blue-500">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ number_format($exemptShare * 100, 1) }}% of this month's sales are exempt. Input VAT that relates to exempt supplies can't be recovered,
                    so part of line 75 may need to be apportioned (on a straight split, @money(round($lines[75] * $exemptShare, 2))). Check with your accountant.
                </p>
            </x-card>
        @endif

        <!-- The form -->
        <x-card>
            <div class="px-6 pt-5 flex flex-wrap justify-between gap-2">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">VAT Form 002 &middot; {{ $monthLabel }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Period {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }} &middot; Currency NGN</span>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full">
                    @foreach(\App\Services\Accounting\VatReturnForm::SECTIONS as $letter => [$title, $rows])
                        <tbody class="border-b border-gray-200 dark:border-gray-700">
                            <tr><th colspan="3" class="pt-4 pb-1 text-left text-sm font-semibold text-gray-700 dark:text-gray-200">Section {{ $letter }}: {{ $title }}</th></tr>
                            @foreach($rows as $no => $label)
                                <tr class="{{ in_array($no, [40, 45, 75, 95, 120]) ? 'font-semibold' : '' }}" data-line="{{ $no }}">
                                    <td class="py-1 pr-4 text-sm text-gray-500 dark:text-gray-400 w-12">{{ $no }}</td>
                                    <td class="py-1 pr-4 text-sm text-gray-900 dark:text-gray-100">{{ $label }}</td>
                                    <td class="py-1 text-sm text-right tabular-nums text-gray-900 dark:text-gray-100">@money($lines[$no])</td>
                                </tr>
                                @if($no === 75 && $importVat > 0)
                                    <tr><td></td><td class="py-1 pl-4 text-xs text-gray-500 dark:text-gray-400">of which VAT on imports</td><td class="py-1 text-xs text-right tabular-nums text-gray-500">@money($importVat)</td></tr>
                                @endif
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
                @if($outOfScope != 0)
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Sales outside the scope of VAT, not on the form: @money($outOfScope).</p>
                @endif
            </div>
        </x-card>

        <!-- Reconciliation to the ledger -->
        <x-card title="Reconciliation to the ledger">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="{{ $th }} text-left"></th>
                            <th class="{{ $th }} text-right">Output VAT ({{ $ledger['outputAccount'] }})</th>
                            <th class="{{ $th }} text-right">Input VAT ({{ $ledger['inputAccount'] }})</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @php($o = $reconciliation['output'])
                        @php($n = $reconciliation['input'])
                        <tr><td class="{{ $td }}">VAT on the document schedules</td><td class="{{ $td }} text-right">@money($o['documents'])</td><td class="{{ $td }} text-right">@money($n['documents'])</td></tr>
                        <tr><td class="{{ $td }}">VAT posted without a document (journals)</td><td class="{{ $td }} text-right">@money($o['other'])</td><td class="{{ $td }} text-right">@money($n['other'])</td></tr>
                        <tr><td class="{{ $td }}">Other differences</td><td class="{{ $td }} text-right">@money($o['unexplained'])</td><td class="{{ $td }} text-right">@money($n['unexplained'])</td></tr>
                        <tr class="font-semibold"><td class="{{ $td }}">Ledger movement for the month</td><td class="{{ $td }} text-right">@money($o['ledger'])</td><td class="{{ $td }} text-right">@money($n['ledger'])</td></tr>
                        <tr class="font-semibold"><td class="{{ $td }}">On the return (lines 45 and 75)</td><td class="{{ $td }} text-right">@money($o['return'])</td><td class="{{ $td }} text-right">@money($n['return'])</td></tr>
                        <tr data-reconciliation-difference><td class="{{ $td }}">Difference, return vs ledger</td><td class="{{ $td }} text-right">@money($o['difference'])</td><td class="{{ $td }} text-right">@money($n['difference'])</td></tr>
                    </tbody>
                </table>
                @foreach(['Output' => $o['otherLines'], 'Input' => $n['otherLines']] as $side => $others)
                    @if($others->isNotEmpty())
                        <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $side }} VAT posted without a document</p>
                        <ul class="mt-1 text-sm text-gray-600 dark:text-gray-400 list-disc ml-5">
                            @foreach($others as $other)
                                <li>{{ $other->date->format('d/m/Y') }} {{ $other->number }} {{ $other->description }}: @money($other->vat)</li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            </div>
        </x-card>

        <!-- Schedules -->
        @foreach($schedules as [$title, $partyLabel, $rows])
            <x-card :title="$title">
                <div class="p-6 overflow-x-auto">
                    @if($rows->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nothing this month.</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="{{ $th }} text-left">{{ $partyLabel }}</th>
                                    <th class="{{ $th }} text-left">TIN</th>
                                    <th class="{{ $th }} text-left">Invoice no.</th>
                                    <th class="{{ $th }} text-left">Date</th>
                                    <th class="{{ $th }} text-left">Description</th>
                                    <th class="{{ $th }} text-left">VAT status</th>
                                    <th class="{{ $th }} text-right">Amount (excl. VAT)</th>
                                    <th class="{{ $th }} text-right">VAT</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($rows as $row)
                                    <tr>
                                        <td class="{{ $td }}">{{ $row->party }}</td>
                                        <td class="{{ $td }}">{{ $row->tin }}</td>
                                        <td class="{{ $td }}">{{ $row->number }}</td>
                                        <td class="{{ $td }} whitespace-nowrap">{{ $row->date->format('d/m/Y') }}</td>
                                        <td class="{{ $td }}">{{ $row->description }}</td>
                                        <td class="{{ $td }} {{ $row->treatment === null ? 'text-amber-600' : '' }}">{{ $treatments[$row->treatment] ?? 'Not classified' }}</td>
                                        <td class="{{ $td }} text-right tabular-nums">@money($row->net)</td>
                                        <td class="{{ $td }} text-right tabular-nums">@money($row->vat)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold">
                                    <td colspan="6" class="{{ $td }}">Total</td>
                                    <td class="{{ $td }} text-right tabular-nums">@money($rows->sum('net'))</td>
                                    <td class="{{ $td }} text-right tabular-nums">@money($rows->sum('vat'))</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </div>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
