@php
    $btn = 'inline-flex items-center justify-center px-3 py-2 border rounded-md font-semibold text-xs uppercase tracking-widest transition';
    $secondary = $btn.' bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600';
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $bom = $order->billOfMaterial;
    $finished = $bom?->item;
    $unit = $finished?->unit ? ' '.$finished->unit : '';
    $breakdown = $order->isBreakdown();
    $done = $order->isCompleted();
    $planned = $order->plannedQuantity();
    $fromName = \App\Models\Warehouse::nameIfMany($order->warehouse_id);
    $toName = \App\Models\Warehouse::nameIfMany($order->to_warehouse_id);
    $plannedUnitCost = null;
    if ($done && ! $breakdown && $planned > 0 && abs((float) $order->quantity_made - $planned) > 0.00001) {
        // What each would have cost had the planned quantity come out of the same inputs.
        $plannedUnitCost = round((float) $order->total_cost / $planned, 2);
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $order->kindLabel() }} {{ $order->order_number }}</h2>
                <x-status-badge :status="$order->status" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('assembly-orders.print', $order) }}" target="_blank" class="{{ $secondary }}">Print production sheet</a>
                @if($order->isDraft())
                    <a href="{{ route('assembly-orders.edit', $order) }}" class="{{ $secondary }}">Edit</a>
                @endif
                <a href="{{ route('assembly-orders.index') }}" class="{{ $secondary }}">All assembly orders</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <x-card>
                        <div class="p-4 sm:p-6 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <div class="col-span-2 md:col-span-1"><p class="text-gray-500 dark:text-gray-400">{{ $breakdown ? 'Broken down' : 'Making' }}</p>
                                @if($finished)
                                    <a href="{{ route('inventory.show', $finished->id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $finished->name }}</a>
                                @endif
                                @if($bom)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">@can('view items')<a href="{{ route('bill-of-materials.show', $bom) }}" class="hover:underline">{{ $bom->label() }}</a>@else{{ $bom->label() }}@endcan</span>
                                @endif
                            </div>
                            <div><p class="text-gray-500 dark:text-gray-400">Date</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $order->assembly_date?->format('M d, Y') }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Planned</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $fmt($planned) }}{{ $unit }}</p></div>
                            @if($done)
                                <div><p class="text-gray-500 dark:text-gray-400">{{ $breakdown ? 'Broken down' : 'Made' }}</p>
                                    <p class="font-medium {{ abs((float) $order->quantity_made - $planned) > 0.00001 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $fmt($order->quantity_made) }}{{ $unit }}</p></div>
                            @endif
                            @if($fromName || $toName)
                                <div><p class="text-gray-500 dark:text-gray-400">{{ $breakdown ? 'Items from' : 'Components from' }}</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $fromName }}</p></div>
                                <div><p class="text-gray-500 dark:text-gray-400">{{ $breakdown ? 'Parts to' : 'Finished goods to' }}</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $toName }}</p></div>
                            @endif
                            <div><p class="text-gray-500 dark:text-gray-400">Entered by</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $order->createdBy?->name ?? '—' }}</p></div>
                        </div>
                    </x-card>

                    <x-card>
                        <div class="p-4 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $breakdown ? 'Parts back in stock' : 'Components' }}</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead><tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                        <th class="px-3 py-2 text-left">Item</th>
                                        <th class="px-3 py-2 text-right">Planned</th>
                                        @if($done)
                                            <th class="px-3 py-2 text-right">{{ $breakdown ? 'Got back' : 'Used' }}</th>
                                            <th class="px-3 py-2 text-right hidden sm:table-cell">Cost</th>
                                        @elseif($order->isDraft() && ! $breakdown)
                                            <th class="px-3 py-2 text-right">Free now</th>
                                        @endif
                                    </tr></thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                        @forelse($order->items as $line)
                                            @php $lineUnit = $line->item?->unit ? ' '.$line->item->unit : ''; @endphp
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <a href="{{ route('inventory.show', $line->item_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $line->item?->name }}</a>
                                                    @if($done)<span class="sm:hidden block text-xs text-gray-500 dark:text-gray-400">Cost @money($line->cost)</span>@endif
                                                </td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fmt($line->planned_quantity) }}{{ $lineUnit }}</td>
                                                @if($done)
                                                    <td class="px-3 py-2 text-right whitespace-nowrap font-medium {{ abs((float) $line->quantity - (float) $line->planned_quantity) > 0.00001 ? 'text-amber-700 dark:text-amber-400' : '' }}">{{ $fmt($line->quantity) }}{{ $lineUnit }}</td>
                                                    <td class="px-3 py-2 text-right whitespace-nowrap hidden sm:table-cell">@money($line->cost)
                                                        @if((float) $line->quantity > 0)<span class="block text-xs text-gray-500 dark:text-gray-400">@money($line->unitCost()) each</span>@endif
                                                    </td>
                                                @elseif($order->isDraft() && ! $breakdown)
                                                    @php $freeQty = $free[$line->item_id] ?? 0; @endphp
                                                    <td class="px-3 py-2 text-right whitespace-nowrap {{ $freeQty + 0.00001 < (float) $line->planned_quantity ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">{{ $fmt($freeQty) }}{{ $lineUnit }}</td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-3 py-4 text-gray-500 dark:text-gray-400">The components come from {{ $bom?->label() }} when the build is completed.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </x-card>

                    @if($order->costs->isNotEmpty())
                        <x-card>
                            <div class="p-4 sm:p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Extra costs</h3>
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead><tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                        <th class="px-3 py-2 text-left">Cost</th>
                                        <th class="px-3 py-2 text-right">Planned</th>
                                        @if($done)<th class="px-3 py-2 text-right">Actual</th>@endif
                                    </tr></thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                        @foreach($order->costs as $cost)
                                            <tr>
                                                <td class="px-3 py-2">{{ $cost->description }}
                                                    <span class="block text-xs text-gray-500 dark:text-gray-400">From {{ $cost->account ? $cost->account->account_code.' '.$cost->account->name : 'Production Costs Applied' }}</span>
                                                </td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">@money($cost->planned_amount)</td>
                                                @if($done)<td class="px-3 py-2 text-right whitespace-nowrap font-medium">@money($cost->amount)</td>@endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-card>
                    @endif

                    @if($done)
                        <x-card>
                            <div class="p-4 sm:p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Cost</h3>
                                <dl class="space-y-1 text-sm">
                                    <div class="flex justify-between gap-4 text-gray-700 dark:text-gray-300"><dt>{{ $breakdown ? 'Cost of the items broken down' : 'Components used' }}</dt><dd class="whitespace-nowrap">@money($order->components_cost)</dd></div>
                                    @unless($breakdown)
                                        <div class="flex justify-between gap-4 text-gray-700 dark:text-gray-300"><dt>Extra costs</dt><dd class="whitespace-nowrap">@money($order->extra_cost)</dd></div>
                                    @endunless
                                    <div class="flex justify-between gap-4 font-semibold text-gray-900 dark:text-gray-100 border-t border-gray-200 dark:border-gray-700 pt-2"><dt>Total cost</dt><dd class="whitespace-nowrap">@money($order->total_cost)</dd></div>
                                    <div class="flex justify-between gap-4 text-gray-900 dark:text-gray-100"><dt>{{ $breakdown ? 'Per item broken down' : 'Cost of each '.($finished?->unit ?: 'unit').' made' }} ({{ $fmt($order->quantity_made) }})</dt><dd class="whitespace-nowrap font-semibold">@money($order->unit_cost)</dd></div>
                                </dl>
                                @if($plannedUnitCost !== null)
                                    <p class="form-help mt-3">{{ $fmt($order->quantity_made) }} made instead of {{ $fmt($planned) }}, so each costs @money($order->unit_cost) instead of @money($plannedUnitCost).</p>
                                @endif
                                <p class="form-help mt-3">
                                    @if($breakdown)
                                        The parts went back into stock at the cost of the items broken down, shared out by what each part costs.
                                    @else
                                        The finished goods went into stock at this cost. Wastage is included: everything used counts as part of the product's cost.
                                    @endif
                                </p>
                            </div>
                        </x-card>
                    @endif

                    @if($order->notes)
                        <x-card>
                            <div class="p-4 sm:p-6 text-sm text-gray-700 dark:text-gray-300">
                                <p class="font-medium text-gray-900 dark:text-gray-100">Notes</p>
                                <p class="whitespace-pre-line">{{ $order->notes }}</p>
                            </div>
                        </x-card>
                    @endif
                </div>

                <div class="space-y-6">
                    @if($order->isDraft())
                        <x-card>
                            <div class="p-4 sm:p-6 space-y-3">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Next step</h3>
                                <button type="button" class="btn-primary w-full" data-open-modal="complete-order">{{ $breakdown ? 'Complete break-down' : 'Complete build' }}</button>
                                <form method="POST" action="{{ route('assembly-orders.cancel', $order) }}" data-confirm="Cancel {{ $order->order_number }}? Nothing has moved, so nothing changes in stock.">@csrf
                                    <button type="submit" class="{{ $secondary }} w-full">Cancel order</button>
                                </form>
                                <form method="POST" action="{{ route('assembly-orders.destroy', $order) }}" data-confirm="Delete draft {{ $order->order_number }}?">@csrf @method('DELETE')
                                    <button type="submit" class="{{ $btn }} w-full bg-white dark:bg-gray-800 border-red-300 text-red-700 dark:text-red-400 hover:bg-red-50">Delete</button>
                                </form>
                            </div>
                        </x-card>
                    @elseif($done)
                        <x-card>
                            <div class="p-4 sm:p-6 space-y-3 text-sm">
                                <p class="text-gray-600 dark:text-gray-400">
                                    @if($breakdown)
                                        Made a mistake? Undo puts the items back together, as long as the parts are all still in stock.
                                    @else
                                        Made a mistake? Undo puts the components back at the cost they left at and takes the finished goods out again, as long as none of them have been sold or used.
                                    @endif
                                </p>
                                <form method="POST" action="{{ route('assembly-orders.undo', $order) }}" data-confirm="Undo {{ $order->order_number }}? It goes back to a draft.">@csrf
                                    <button type="submit" class="{{ $secondary }} w-full">{{ $breakdown ? 'Undo break-down' : 'Undo build' }}</button>
                                </form>
                                @if($bom && $bom->is_active)
                                    <a href="{{ route('assembly-orders.create', ['bill' => $bom->id, 'kind' => $order->kind]) }}" class="{{ $secondary }} w-full">{{ $breakdown ? 'Break down more' : 'Build again' }}</a>
                                @endif
                            </div>
                        </x-card>
                    @else
                        <x-card>
                            <div class="p-4 sm:p-6 space-y-3 text-sm">
                                <p class="text-gray-600 dark:text-gray-400">This order was cancelled. Nothing moved in stock.</p>
                                <form method="POST" action="{{ route('assembly-orders.destroy', $order) }}" data-confirm="Delete {{ $order->order_number }}?">@csrf @method('DELETE')
                                    <button type="submit" class="{{ $btn }} w-full bg-white dark:bg-gray-800 border-red-300 text-red-700 dark:text-red-400 hover:bg-red-50">Delete</button>
                                </form>
                            </div>
                        </x-card>
                    @endif

                    @if($journals->isNotEmpty())
                        <x-card>
                            <div class="p-4 sm:p-6 text-sm space-y-2">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Extra costs posted</h3>
                                <p class="text-gray-600 dark:text-gray-400">Inventory (debit), and the account each cost comes from (credit). Components becoming finished goods posts nothing: both are Inventory.</p>
                                @foreach($journals as $journal)
                                    <p>
                                        @can('view journals')
                                            <a href="{{ route('journals.show', $journal) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Journal {{ $journal->journal_number }}</a>
                                        @else
                                            Journal {{ $journal->journal_number }}
                                        @endcan
                                        <span class="text-gray-500 dark:text-gray-400">@money($journal->total_debit){{ str_starts_with((string) $journal->reference, 'REV-') ? ', reversal' : ($journal->status === 'reversed' ? ', reversed' : '') }}</span>
                                    </p>
                                @endforeach
                            </div>
                        </x-card>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($order->isDraft())
        <x-modal name="complete-order" title="{{ $breakdown ? 'Complete break-down' : 'Complete build' }} {{ $order->order_number }}" maxWidth="lg" :show="$errors->any()">
            <form method="POST" action="{{ route('assembly-orders.complete', $order) }}" class="p-6 space-y-4">
                @csrf
                <x-lock-date-notice field="assembly_date" />
                <p class="text-sm text-gray-600 dark:text-gray-400">Change anything that was different from the plan.</p>
                <div class="flex items-center justify-between gap-3">
                    <label for="quantity_made" class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $breakdown ? 'How many were broken down' : 'How many were made' }}{{ $unit ? ' ('.trim($unit).')' : '' }}</label>
                    <input type="number" id="quantity_made" name="quantity_made" value="{{ old('quantity_made', $planned) }}" min="0" step="any" inputmode="decimal" class="form-control text-sm w-32">
                </div>
                @error('quantity_made')<p class="form-error">{{ $message }}</p>@enderror
                @if($order->items->isNotEmpty())
                    <div>
                        <p class="form-label">{{ $breakdown ? 'Parts got back' : 'Components used' }}</p>
                        <div class="space-y-2">
                            @foreach($order->items as $i => $line)
                                <div class="flex items-center justify-between gap-3">
                                    <label for="used-{{ $line->id }}" class="text-sm text-gray-700 dark:text-gray-300">{{ $line->item?->name }}{{ $line->item?->unit ? ' ('.$line->item->unit.')' : '' }}</label>
                                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $line->id }}">
                                    <input type="number" id="used-{{ $line->id }}" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", (float) $line->planned_quantity) }}"
                                           min="0" step="any" inputmode="decimal" class="form-control text-sm w-32">
                                </div>
                                @error("items.$i.quantity")<p class="form-error">{{ $message }}</p>@enderror
                            @endforeach
                        </div>
                        @error('items')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                @endif
                @if($order->costs->isNotEmpty())
                    <div>
                        <p class="form-label">Extra costs</p>
                        <div class="space-y-2">
                            @foreach($order->costs as $i => $cost)
                                <div class="flex items-center justify-between gap-3">
                                    <label for="cost-{{ $cost->id }}" class="text-sm text-gray-700 dark:text-gray-300">{{ $cost->description }}</label>
                                    <input type="hidden" name="costs[{{ $i }}][id]" value="{{ $cost->id }}">
                                    <input type="number" id="cost-{{ $cost->id }}" name="costs[{{ $i }}][amount]" value="{{ old("costs.$i.amount", (float) $cost->planned_amount) }}"
                                           min="0" step="0.01" inputmode="decimal" class="form-control text-sm w-32">
                                </div>
                                @error("costs.$i.amount")<p class="form-error">{{ $message }}</p>@enderror
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button type="button" class="{{ $secondary }}" data-close-modal="complete-order">Close</button>
                    <button type="submit" class="btn-primary">{{ $breakdown ? 'Complete break-down' : 'Complete build' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
