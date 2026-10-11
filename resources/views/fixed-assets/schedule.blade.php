{{-- Depreciation for the year across all assets (tables plan T7). Amounts in ₦. --}}
@php
    $labels = \App\Livewire\FixedAssets\FixedAssetsTable::LABELS;
    $statuses = collect($labels)->only(['active', 'under_maintenance', 'idle'])->all();
    $tones = ['active' => 'active', 'under_maintenance' => 'pending', 'idle' => 'inactive', 'fully_depreciated' => 'completed'];
    $years = collect(range((int) date('Y') + 5, (int) date('Y') - 10))->mapWithKeys(fn ($y) => [$y => (string) $y]);
    $rows = collect($scheduleData);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="'Depreciation for '.$year" description="What each asset will be charged this year, and where it stands now. Amounts in ₦.">
            <x-slot name="more">
                <x-table.menu-item :href="route('fixed-assets.register')">Asset register</x-table.menu-item>
                <x-table.menu-item :href="route('fixed-assets.index')">All fixed assets</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                <x-table.dropdown label="Export" align="right">
                    <x-table.menu-item print>Print</x-table.menu-item>
                </x-table.dropdown>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <x-report.sheet :title="'Depreciation for '.$year" :period="'Year '.$year">
        <x-report.filters :action="route('fixed-assets.depreciation-schedule')" button="Show">
            <x-report.pick name="year" label="Year" :options="$years" :value="$year" :all="null" />
            <x-report.pick name="category_id" label="Category" :options="$categories->pluck('name', 'id')" :value="request('category_id')" all="All categories" />
            <x-report.pick name="status" label="Status" :options="$statuses" :value="request('status')" all="Any status" />
        </x-report.filters>

        <x-report.stats :cols="3">
            <x-report.stat label="Assets" :value="$rows->count()" hint="With a charge this year" />
            <x-report.stat :label="'Charge for '.$year" :value="\App\Support\Figure::show($totalYearDepreciation)" />
            <x-report.stat label="Book value now" :value="\App\Support\Figure::show($rows->sum(fn ($d) => (float) $d['asset']->book_value))" />
        </x-report.stats>

        @if ($rows->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No depreciation this year" text="None of the assets that match have a charge in this year. Try another year or category." />
            </div>
        @else
            <x-table :caption="'Depreciation for '.$year.', by asset'">
                <x-slot name="head">
                    <x-table.th>Asset</x-table.th>
                    <x-table.th class="hidden md:table-cell">Category</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Cost</x-table.th>
                    <x-table.th num class="hidden lg:table-cell">Depreciated so far</x-table.th>
                    <x-table.th num class="hidden sm:table-cell">Book value</x-table.th>
                    <x-table.th num>Charge for {{ $year }}</x-table.th>
                    <x-table.th class="hidden md:table-cell">Status</x-table.th>
                </x-slot>
                @foreach ($rows as $data)
                    @php $asset = $data['asset']; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('fixed-assets.schedule', $asset) }}" class="tbl-link font-medium">{{ $asset->name }}</a>
                            <div class="text-xs tbl-muted">{{ $asset->asset_number }}</div>
                        </td>
                        <td class="hidden md:table-cell">{{ $asset->category->name ?? '—' }}</td>
                        <td class="num hidden lg:table-cell">@fig($asset->purchase_cost)</td>
                        <td class="num hidden lg:table-cell">@fig($asset->accumulated_depreciation)</td>
                        <td class="num hidden sm:table-cell">@fig($asset->book_value)</td>
                        <td class="num font-medium">@fig($data['year_depreciation'])</td>
                        <td class="hidden md:table-cell"><x-status-badge :status="$tones[$asset->status] ?? 'draft'" :label="$labels[$asset->status] ?? \Illuminate\Support\Str::headline($asset->status)" /></td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <th scope="row">Total</th>
                        <td class="hidden md:table-cell"></td>
                        <td class="num hidden lg:table-cell">@fig($rows->sum(fn ($d) => (float) $d['asset']->purchase_cost))</td>
                        <td class="num hidden lg:table-cell">@fig($rows->sum(fn ($d) => (float) $d['asset']->accumulated_depreciation))</td>
                        <td class="num hidden sm:table-cell">@fig($rows->sum(fn ($d) => (float) $d['asset']->book_value))</td>
                        <td class="num">@fig($totalYearDepreciation)</td>
                        <td class="hidden md:table-cell"></td>
                    </tr>
                </x-slot>
            </x-table>
        @endif
    </x-report.sheet>
</x-app-layout>
