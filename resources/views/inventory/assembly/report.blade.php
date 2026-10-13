{{-- Production report (tables plan T7): what was made and broken down in a period, and what it cost. Amounts in ₦. --}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0';
    $groups = collect($rows)->groupBy('kind');
    $period = $from->format('j M Y').' to '.$to->format('j M Y');
    $built = $groups->get('build', collect());
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Production report" :description="'What was made and broken down from '.$period.', and what it cost. Amounts in ₦.'">
            <x-slot name="more">
                <x-table.menu-item :href="route('assembly-orders.index')">All assembly orders</x-table.menu-item>
                <x-table.menu-item :href="route('bill-of-materials.index')">Bills of materials</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                <x-table.dropdown label="Export" align="right">
                    <x-table.menu-item print>Print</x-table.menu-item>
                </x-table.dropdown>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <x-report.sheet title="Production report" :period="$period">
        <x-report.filters :action="route('assembly-orders.report')" button="Show">
            <x-report.date name="from" label="From" :value="$from->toDateString()" />
            <x-report.date name="to" label="To" :value="$to->toDateString()" />
        </x-report.filters>

        <x-report.stats :cols="3">
            <x-report.stat label="Builds finished" :value="$built->sum('orders')" />
            <x-report.stat label="Cost of what was made" :value="\App\Support\Figure::show($built->sum('total_cost'))" />
            <x-report.stat label="Of which extra costs" :value="\App\Support\Figure::show($built->sum('extra_cost'))" hint="Labour, power, packaging" />
        </x-report.stats>

        @if ($groups->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="Nothing made in this period" :text="'No builds were finished from '.$period.'. Try a wider period.'" />
            </div>
        @endif

        @foreach (['build' => 'Made', 'breakdown' => 'Broken down'] as $kind => $heading)
            @continue(! $groups->has($kind))
            <section class="space-y-3" aria-labelledby="kind-{{ $kind }}">
                <h3 id="kind-{{ $kind }}" class="text-base font-semibold text-gray-900 dark:text-white">{{ $heading }}</h3>
                <x-table :caption="$heading.', '.$period">
                    <x-slot name="head">
                        <x-table.th>Item</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Orders</x-table.th>
                        <x-table.th num>Quantity</x-table.th>
                        @if ($kind === 'build')
                            <x-table.th num class="hidden md:table-cell">Components</x-table.th>
                            <x-table.th num class="hidden md:table-cell">Extra costs</x-table.th>
                        @endif
                        <x-table.th num>Total cost</x-table.th>
                        <x-table.th num class="hidden sm:table-cell">Each</x-table.th>
                    </x-slot>
                    @foreach ($groups[$kind] as $row)
                        <tr>
                            <td><a href="{{ route('inventory.show', $row->item_id) }}" class="tbl-link">{{ $row->item_name }}</a></td>
                            <td class="num hidden sm:table-cell">{{ $row->orders }}</td>
                            <td class="num">{{ $fmt($row->quantity) }} <span class="tbl-muted">{{ $row->unit }}</span></td>
                            @if ($kind === 'build')
                                <td class="num hidden md:table-cell">@fig($row->components_cost)</td>
                                <td class="num hidden md:table-cell {{ \App\Support\Figure::tone($row->extra_cost) }}">@fig($row->extra_cost)</td>
                            @endif
                            <td class="num font-medium">@fig($row->total_cost)</td>
                            <td class="num hidden sm:table-cell">@fig((float) $row->quantity > 0 ? (float) $row->total_cost / (float) $row->quantity : 0)</td>
                        </tr>
                    @endforeach
                    <x-slot name="foot">
                        <tr>
                            <th scope="row">Total</th>
                            <td class="num hidden sm:table-cell">{{ $groups[$kind]->sum('orders') }}</td>
                            <td></td>
                            @if ($kind === 'build')
                                <td class="num hidden md:table-cell">@fig($groups[$kind]->sum('components_cost'))</td>
                                <td class="num hidden md:table-cell">@fig($groups[$kind]->sum('extra_cost'))</td>
                            @endif
                            <td class="num">@fig($groups[$kind]->sum('total_cost'))</td>
                            <td class="hidden sm:table-cell"></td>
                        </tr>
                    </x-slot>
                </x-table>
            </section>
        @endforeach
    </x-report.sheet>
</x-app-layout>
