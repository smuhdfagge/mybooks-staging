{{-- Depreciation schedule for one asset (tables plan T7): what has been charged so far, and the plan to the end of its life. Amounts in ₦. --}}
@php
    $methods = \App\Models\FixedAsset::getDepreciationMethods();
    $recorded = $depreciations->count();
    $life = rtrim(rtrim(number_format((float) $fixedAsset->useful_life, 2), '0'), '.');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$fixedAsset->name.': depreciation'" :description="$fixedAsset->asset_number.' · '.($methods[$fixedAsset->depreciation_method] ?? \Illuminate\Support\Str::headline((string) $fixedAsset->depreciation_method)).' over '.$life.' '.\Illuminate\Support\Str::plural('year', (float) $fixedAsset->useful_life).'. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('fixed-assets.show', $fixedAsset)">Asset details</x-table.menu-item>
                <x-table.menu-item :href="route('fixed-assets.depreciation-schedule')">All assets' schedule</x-table.menu-item>
                <x-table.menu-item :href="route('fixed-assets.index')">All fixed assets</x-table.menu-item>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-6">
        <x-report.stats :cols="4">
            <x-report.stat label="Cost" :value="\App\Support\Figure::show($fixedAsset->purchase_cost)" :hint="'Bought '.$fixedAsset->purchase_date?->format('j M Y')" />
            <x-report.stat label="Depreciated so far" :value="\App\Support\Figure::show($fixedAsset->accumulated_depreciation)" :hint="$recorded ? $recorded.' of '.count($schedule).' months charged' : null" />
            <x-report.stat label="Book value now" :value="\App\Support\Figure::show($fixedAsset->book_value)" />
            <x-report.stat label="Left at the end" :value="\App\Support\Figure::show($fixedAsset->salvage_value)" :hint="'In use from '.($fixedAsset->in_service_date?->format('j M Y') ?? '—')" />
        </x-report.stats>

        @if ($recorded > 0)
            <section class="space-y-3" aria-labelledby="charged-title">
                <h3 id="charged-title" class="text-base font-semibold text-gray-900 dark:text-white">Charged so far</h3>
                <x-table caption="Depreciation charged so far">
                    <x-slot name="head">
                        <x-table.th num>Month</x-table.th>
                        <x-table.th>Date</x-table.th>
                        <x-table.th num>Charge</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Total so far</x-table.th>
                        <x-table.th num>Book value</x-table.th>
                        <x-table.th class="hidden sm:table-cell">Journal</x-table.th>
                    </x-slot>
                    @foreach ($depreciations as $depreciation)
                        <tr>
                            <td class="num tbl-muted">{{ $depreciation->period_number }}</td>
                            <td class="">{{ $depreciation->depreciation_date->format('j M Y') }}</td>
                            <td class="num">@fig($depreciation->depreciation_amount)</td>
                            <td class="num hidden sm:table-cell">@fig($depreciation->accumulated_depreciation)</td>
                            <td class="num font-medium">@fig($depreciation->book_value)</td>
                            <td class="hidden sm:table-cell">
                                @if ($depreciation->journal_id)
                                    <a href="{{ route('journals.show', $depreciation->journal_id) }}" class="tbl-link">View journal</a>
                                @else
                                    <span class="tbl-zero">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <x-slot name="foot">
                        <tr>
                            <th scope="row" colspan="2">Total charged</th>
                            <td class="num">@fig($depreciations->sum('depreciation_amount'))</td>
                            <td class="hidden sm:table-cell"></td>
                            <td></td>
                            <td class="hidden sm:table-cell"></td>
                        </tr>
                    </x-slot>
                </x-table>
            </section>
        @endif

        <section class="space-y-3" aria-labelledby="plan-title">
            <h3 id="plan-title" class="text-base font-semibold text-gray-900 dark:text-white">Full plan</h3>
            @if (count($schedule) === 0)
                <div class="tbl-wrap">
                    <x-table.empty title="No plan to show" text="This asset has nothing left to depreciate, or its cost, life or in-use date is missing." />
                </div>
            @else
                <x-table caption="Depreciation plan, month by month">
                    <x-slot name="head">
                        <x-table.th num>Month</x-table.th>
                        <x-table.th>Date</x-table.th>
                        <x-table.th num class="hidden md:table-cell">Value at start</x-table.th>
                        <x-table.th num>Charge</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Total so far</x-table.th>
                        <x-table.th num>Value at end</x-table.th>
                    </x-slot>
                    @php $opening = (float) $fixedAsset->purchase_cost; @endphp
                    @foreach ($schedule as $row)
                        @php $done = $row['period'] <= $recorded; @endphp
                        <tr>
                            <td class="num tbl-muted">{{ $row['period'] }}</td>
                            <td class="">
                                {{ \Illuminate\Support\Carbon::parse($row['date'])->format('j M Y') }}
                                @if ($done)
                                    <x-status-badge status="completed" label="Charged" />
                                @endif
                            </td>
                            <td class="num hidden md:table-cell">@fig($opening)</td>
                            <td class="num">@fig($row['depreciation_amount'])</td>
                            <td class="num hidden sm:table-cell">@fig($row['accumulated_depreciation'])</td>
                            <td class="num font-medium">@fig($row['book_value'])</td>
                        </tr>
                        @php $opening = (float) $row['book_value']; @endphp
                    @endforeach
                    <x-slot name="foot">
                        <tr>
                            <th scope="row" colspan="2">Total over its life</th>
                            <td class="hidden md:table-cell"></td>
                            <td class="num">@fig(collect($schedule)->sum('depreciation_amount'))</td>
                            <td class="hidden sm:table-cell"></td>
                            <td></td>
                        </tr>
                    </x-slot>
                </x-table>
            @endif
        </section>
    </div>
</x-app-layout>
