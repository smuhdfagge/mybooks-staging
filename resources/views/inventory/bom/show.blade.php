@php
    $btn = 'btn-secondary';
    $secondary = 'btn-secondary';
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $unit = $bom->item?->unit ? ' '.$bom->item->unit : '';
    $canBuild = $bom->is_active && auth()->user()->can('adjust inventory');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$bom->label()" description="Bill of materials">
            <x-slot name="badge"><x-status-badge :status="$bom->is_active ? 'active' : 'draft'" :label="$bom->is_active ? 'In use' : 'Not in use'" /></x-slot>
            @if ($canBuild)
                <x-slot name="more">
                    <x-table.menu-item :href="route('assembly-orders.create', ['bill' => $bom->id, 'kind' => 'breakdown'])">Break down</x-table.menu-item>
                </x-slot>
            @endif
            <x-slot name="actions">
                @can('edit items')
                    <a href="{{ route('bill-of-materials.edit', $bom) }}" class="btn-secondary">Edit</a>
                @endcan
                @if ($canBuild)
                    <a href="{{ route('assembly-orders.create', ['bill' => $bom->id]) }}" class="btn-new">Build</a>
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <x-card>
                        <div class="p-4 sm:p-6">
                            <p class="text-sm text-gray-600 dark:text-gray-400">One batch makes</p>
                            <p class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $fmt($bom->output_quantity) }}{{ $unit }}
                                @if($bom->item)<a href="{{ route('inventory.show', $bom->item_id) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $bom->item->name }}</a>@endif
                            </p>

                            <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mt-6 mb-2">Components per batch</h3>
                            <x-table caption="Components per batch">
<x-slot name="head">
                                        <x-table.th>Item</x-table.th>
                                        <x-table.th num>Quantity</x-table.th>
                                        <x-table.th num class="hidden sm:table-cell">Wastage</x-table.th>
                                        <x-table.th num class="hidden sm:table-cell">Cost each</x-table.th>
                                        <x-table.th num>Est. cost</x-table.th>
</x-slot>

                                        @foreach($estimate['lines'] as $row)
                                            @php $line = $row['line']; $lineUnit = $line->item?->unit ? ' '.$line->item->unit : ''; @endphp
                                            <tr>
                                                <td><a href="{{ route('inventory.show', $line->item_id) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $line->item?->name }}</a>
                                                    @if((float) $line->waste_percentage > 0)<span class="sm:hidden block text-xs text-gray-500 dark:text-gray-400">+{{ $fmt($line->waste_percentage) }}% wastage</span>@endif
                                                </td>
                                                <td class="num">{{ $fmt($line->quantity) }}{{ $lineUnit }}</td>
                                                <td class="num hidden sm:table-cell">{{ (float) $line->waste_percentage > 0 ? $fmt($line->waste_percentage).'%' : '—' }}</td>
                                                <td class="num hidden sm:table-cell">@fig($row['unit_cost'], false)</td>
                                                <td class="num">@fig($row['cost'], false)</td>
                                            </tr>
                                        @endforeach
</x-table>

                            @if($bom->costs->isNotEmpty())
                                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mt-6 mb-2">Extra costs per batch</h3>
                                <x-table caption="Extra costs per batch">
<x-slot name="head">
</x-slot>
                                    
                                        @foreach($bom->costs as $cost)
                                            <tr>
                                                <td>{{ $cost->description }}
                                                    <span class="block text-xs text-gray-500 dark:text-gray-400">Credited to {{ $cost->account ? $cost->account->account_code.' '.$cost->account->name : 'Production Costs Applied' }}</span>
                                                </td>
                                                <td class="num">@fig($cost->amount, false)</td>
                                            </tr>
                                        @endforeach
</x-table>
                            @endif
                        </div>
                    </x-card>

                    @if($bom->description)
                        <x-card>
                            <div class="p-4 sm:p-6 text-sm text-gray-700 dark:text-gray-300">
                                <p class="font-medium text-gray-900 dark:text-gray-100">Notes</p>
                                <p class="whitespace-pre-line">{{ $bom->description }}</p>
                            </div>
                        </x-card>
                    @endif
                </div>

                <div class="space-y-6">
                    <x-card>
                        <div class="p-4 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Estimated cost</h3>
                            <dl class="space-y-1 text-sm">
                                <div class="flex justify-between gap-4 text-gray-700 dark:text-gray-300"><dt>Components</dt><dd class="whitespace-nowrap">@money($estimate['components'])</dd></div>
                                <div class="flex justify-between gap-4 text-gray-700 dark:text-gray-300"><dt>Extra costs</dt><dd class="whitespace-nowrap">@money($estimate['extra'])</dd></div>
                                <div class="flex justify-between gap-4 font-semibold text-gray-900 dark:text-gray-100 border-t border-gray-200 dark:border-gray-700 pt-2"><dt>One batch</dt><dd class="whitespace-nowrap">@money($estimate['total'])</dd></div>
                                <div class="flex justify-between gap-4 font-semibold text-gray-900 dark:text-gray-100"><dt>Each {{ $bom->item?->unit ?: 'unit' }}</dt><dd class="whitespace-nowrap">@money($estimate['per_unit'])</dd></div>
                            </dl>
                            <p class="form-help mt-2">At today's average cost of the components in stock (their cost price when none is in stock).</p>
                        </div>
                    </x-card>

                    <x-card>
                        <div class="p-4 sm:p-6 text-sm">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Recent builds</h3>
                            @forelse($orders as $order)
                                <div class="flex justify-between gap-2 py-1">
                                    @can('adjust inventory')
                                        <a href="{{ route('assembly-orders.show', $order) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $order->order_number }}</a>
                                    @else
                                        <span>{{ $order->order_number }}</span>
                                    @endcan
                                    <span class="text-gray-500 dark:text-gray-400">{{ $order->assembly_date?->format('j M Y') }}</span>
                                    <x-status-badge :status="$order->status" />
                                </div>
                            @empty
                                <p class="text-gray-500 dark:text-gray-400">Not used yet.</p>
                            @endforelse
                        </div>
                    </x-card>

                    @can('delete items')
                        @if(! $bom->assemblyOrders()->exists())
                            <form method="POST" action="{{ route('bill-of-materials.destroy', $bom) }}" data-confirm="Delete {{ $bom->label() }}?">@csrf @method('DELETE')
                                <button type="submit" class="{{ $btn }} w-full bg-white dark:bg-gray-800 border-red-300 text-red-700 dark:text-red-300 hover:bg-red-50">Delete bill of materials</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
