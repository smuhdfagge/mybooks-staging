{{-- Fixed asset register (tables plan T7): every asset bought by the "as at" date, its cost and what is left of it. Amounts in ₦. --}}
@php
    $labels = \App\Livewire\FixedAssets\FixedAssetsTable::LABELS;
    $tones = ['active' => 'active', 'under_maintenance' => 'pending', 'idle' => 'inactive', 'fully_depreciated' => 'completed', 'disposed' => 'cancelled', 'sold' => 'closed'];
    $methods = \App\Models\FixedAsset::getDepreciationMethods();
    $asOfText = \Illuminate\Support\Carbon::parse($asOf)->format('j M Y');
    $years = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Asset register" :description="'Every fixed asset bought by '.$asOfText.', what it cost and what is left of it. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('fixed-assets.depreciation-schedule')">Depreciation for the year</x-table.menu-item>
                <x-table.menu-item :href="route('fixed-assets.index')">All fixed assets</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                <x-table.dropdown label="Export" align="right">
                    <x-table.menu-item print>Print</x-table.menu-item>
                </x-table.dropdown>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <x-report.sheet title="Asset register" :period="'As at '.$asOfText">
        <x-report.filters :action="route('fixed-assets.register')" button="Show">
            <x-report.date name="as_of" label="As at" :value="$asOf" />
            <x-report.pick name="category_id" label="Category" :options="$categories->pluck('name', 'id')" :value="$categoryId" all="All categories" />
            <x-report.pick name="status" label="Status" :options="$labels" :value="$status" all="Any status" />
        </x-report.filters>

        <x-report.stats :cols="4">
            <x-report.stat label="Assets" :value="$totalAssets" />
            <x-report.stat label="Cost" :value="\App\Support\Figure::show($totalCost)" />
            <x-report.stat label="Depreciated so far" :value="\App\Support\Figure::show($totalDepreciation)" />
            <x-report.stat label="Book value" :value="\App\Support\Figure::show($totalBookValue)" />
        </x-report.stats>

        @if ($assets->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No assets to show" text="No fixed assets match, or none were bought by this date." />
            </div>
        @else
            <x-table caption="Asset register">
                <x-slot name="head">
                    <x-table.th>Asset</x-table.th>
                    <x-table.th class="hidden md:table-cell">Category</x-table.th>
                    <x-table.th class="hidden lg:table-cell">Bought</x-table.th>
                    <x-table.th class="hidden xl:table-cell">Life and method</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Cost</x-table.th>
                    <x-table.th num class="hidden 2xl:table-cell">Left at the end</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Depreciated so far</x-table.th>
                    <x-table.th num>Book value</x-table.th>
                    <x-table.th class="hidden md:table-cell">Status</x-table.th>
                </x-slot>
                @foreach ($assets as $asset)
                    <tr>
                        <td>
                            <a href="{{ route('fixed-assets.show', $asset) }}" class="tbl-link font-medium">{{ $asset->name }}</a>
                            <div class="text-xs tbl-muted">{{ $asset->asset_number }}{{ $asset->location ? ' · '.$asset->location : '' }}</div>
                        </td>
                        <td class="hidden md:table-cell">{{ $asset->category->name ?? '—' }}</td>
                        <td class="hidden lg:table-cell">{{ $asset->purchase_date?->format('j M Y') ?? '—' }}</td>
                        <td class="hidden xl:table-cell">{{ $years($asset->useful_life) }} {{ \Illuminate\Support\Str::plural('year', (float) $asset->useful_life) }}<div class="text-xs tbl-muted">{{ $methods[$asset->depreciation_method] ?? \Illuminate\Support\Str::headline((string) $asset->depreciation_method) }}</div></td>
                        <td class="num hidden sm:table-cell">@fig($asset->purchase_cost)</td>
                        <td class="num hidden 2xl:table-cell {{ \App\Support\Figure::tone($asset->salvage_value) }}">@fig($asset->salvage_value)</td>
                        <td class="num hidden lg:table-cell">@fig($asset->accumulated_depreciation)</td>
                        <td class="num font-medium">@fig($asset->book_value)</td>
                        <td class="hidden md:table-cell"><x-status-badge :status="$tones[$asset->status] ?? 'draft'" :label="$labels[$asset->status] ?? \Illuminate\Support\Str::headline($asset->status)" /></td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <th scope="row">Total</th>
                        <td class="hidden md:table-cell"></td>
                        <td class="hidden lg:table-cell"></td>
                        <td class="hidden xl:table-cell"></td>
                        <td class="num hidden sm:table-cell">@fig($totalCost)</td>
                        <td class="num hidden 2xl:table-cell">@fig($assets->sum('salvage_value'))</td>
                        <td class="num hidden lg:table-cell">@fig($totalDepreciation)</td>
                        <td class="num">@fig($totalBookValue)</td>
                        <td class="hidden md:table-cell"></td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
