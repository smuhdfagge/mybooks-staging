{{-- Missing Stock Costs (audit MB-31): stock on hand with no recorded cost, which an invoice can't take out until its cost is recorded. --}}
@php
    $qty = fn ($v) => \App\Services\UncostedStock::quantity((float) $v);
    $money = fn ($v) => number_format((float) $v, 2);
    $canRecord = auth()->user()->can('post journals');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Missing stock costs" :description="'Stock on hand with no recorded cost. Invoices can\'t take it out until its cost is recorded. Amounts in '.\App\Support\Money::symbol().'.'">
            <x-slot name="actions"><a href="{{ route('inventory.index') }}" class="btn-secondary">Stock on hand</a></x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-6">
        @if ($missing->isEmpty() && $excess->isEmpty())
            <x-table.empty title="No stock is missing a cost" text="Every item on hand has a recorded cost, so invoices can take it out." />
        @endif

        @if ($missing->isNotEmpty())
            <section class="space-y-3" aria-labelledby="missing-heading">
                <div>
                    <h2 id="missing-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">Stock with no recorded cost</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Usually stock entered before costs were tracked. Enter what each unit cost and record it: this adds the stock's value to Inventory, against Opening Balance Equity, dated today.
                        If the stock isn't really there, count it down on the item's stock page instead.
                    </p>
                    @unless ($canRecord)
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">Recording a cost posts a journal entry, so it needs permission to post journals. Ask an administrator.</p>
                    @endunless
                </div>

                <x-table caption="Stock with no recorded cost">
                    <x-slot name="head">
                        <x-table.th>Item</x-table.th>
                        <x-table.th>Warehouse</x-table.th>
                        <x-table.th num>On hand</x-table.th>
                        <x-table.th num>Sold, not yet released</x-table.th>
                        <x-table.th num>With a cost</x-table.th>
                        <x-table.th num>Missing a cost</x-table.th>
                        <x-table.th num>Unit cost</x-table.th>
                        <x-table.th num>Value</x-table.th>
                        @if ($canRecord)<th scope="col"><span class="sr-only">Record</span></th>@endif
                    </x-slot>
                    @foreach ($missing as $i => $row)
                        @php $formId = 'record-cost-'.$i; @endphp
                        <tr>
                            <td><a href="{{ route('inventory.show', $row['item_id']) }}" class="tbl-link">{{ $row['item_name'] }}</a></td>
                            <td class="tbl-muted">{{ $row['warehouse_name'] }}</td>
                            <td class="num">{{ $qty($row['on_hand']) }}</td>
                            <td class="num {{ $row['awaiting'] > 0 ? '' : 'tbl-zero' }}">{{ $qty($row['awaiting']) }}</td>
                            <td class="num {{ $row['with_cost'] > 0 ? '' : 'tbl-zero' }}">{{ $qty($row['with_cost']) }}</td>
                            <td class="num font-medium">{{ $qty($row['missing']) }}</td>
                            <td class="num">
                                @if ($canRecord)
                                    <label for="{{ $formId }}-cost" class="sr-only">Unit cost of {{ $row['item_name'] }} in {{ $row['warehouse_name'] }}</label>
                                    <input type="number" id="{{ $formId }}-cost" name="unit_cost" form="{{ $formId }}" required min="0.0001" step="0.0001"
                                        value="{{ $row['suggested_cost'] > 0 ? $row['suggested_cost'] : '' }}" placeholder="0.00"
                                        class="form-control w-28 text-right">
                                @else
                                    {{ $row['suggested_cost'] > 0 ? $money($row['suggested_cost']) : '—' }}
                                @endif
                            </td>
                            <td class="num {{ $row['suggested_cost'] > 0 ? '' : 'tbl-zero' }}">{{ $row['suggested_cost'] > 0 ? $money($row['missing'] * $row['suggested_cost']) : '—' }}</td>
                            @if ($canRecord)
                                <td class="text-right">
                                    <form id="{{ $formId }}" method="POST" action="{{ route('stock-costs.store') }}">
                                        @csrf
                                        <input type="hidden" name="item_id" value="{{ $row['item_id'] }}">
                                        <input type="hidden" name="warehouse_id" value="{{ $row['warehouse_id'] }}">
                                        <button type="submit" class="btn-primary whitespace-nowrap">Record cost</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-table>
                <p class="text-xs text-gray-600 dark:text-gray-400">The unit cost starts at the warehouse's average cost, or the item's cost price. Value is worked out from that starting cost.</p>
            </section>
        @endif

        @if ($excess->isNotEmpty())
            <section class="space-y-3" aria-labelledby="excess-heading">
                <div>
                    <h2 id="excess-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">More with a cost than on hand</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">The records say more of this stock has a cost than is on hand. Count it on the item's stock page to put them right.</p>
                </div>
                <x-table caption="More with a cost than on hand">
                    <x-slot name="head">
                        <x-table.th>Item</x-table.th>
                        <x-table.th>Warehouse</x-table.th>
                        <x-table.th num>On hand</x-table.th>
                        <x-table.th num>Sold, not yet released</x-table.th>
                        <x-table.th num>With a cost</x-table.th>
                        <x-table.th num>Difference</x-table.th>
                        <th scope="col"><span class="sr-only">Count</span></th>
                    </x-slot>
                    @foreach ($excess as $row)
                        <tr>
                            <td><a href="{{ route('inventory.show', $row['item_id']) }}" class="tbl-link">{{ $row['item_name'] }}</a></td>
                            <td class="tbl-muted">{{ $row['warehouse_name'] }}</td>
                            <td class="num">{{ $qty($row['on_hand']) }}</td>
                            <td class="num {{ $row['awaiting'] > 0 ? '' : 'tbl-zero' }}">{{ $qty($row['awaiting']) }}</td>
                            <td class="num">{{ $qty($row['with_cost']) }}</td>
                            <td class="num font-medium">{{ $qty(-$row['missing']) }}</td>
                            <td class="text-right"><a href="{{ route('inventory.show', $row['item_id']) }}" class="btn-secondary whitespace-nowrap">Count stock</a></td>
                        </tr>
                    @endforeach
                </x-table>
            </section>
        @endif
    </div>
</x-app-layout>
