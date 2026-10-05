@php
    $btn = 'inline-flex items-center justify-center px-3 py-2 border rounded-md font-semibold text-xs uppercase tracking-widest transition';
    $secondary = $btn.' bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600';
    $status = $transfer->status;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $done = in_array($status, ['received', 'cancelled'], true);
    $statusLabel = ['in_transit' => 'In transit', 'received' => 'Received', 'cancelled' => 'Cancelled', 'draft' => 'Draft'][$status] ?? null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Transfer {{ $transfer->transfer_number }}</h2>
                <x-status-badge :status="$status" :label="$statusLabel" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('stock-transfers.print', $transfer) }}" target="_blank" class="{{ $secondary }}">Print transfer note</a>
                @if($transfer->isDraft())
                    <a href="{{ route('stock-transfers.edit', $transfer) }}" class="{{ $secondary }}">Edit</a>
                @endif
                <a href="{{ route('stock-transfers.index') }}" class="{{ $secondary }}">All transfers</a>
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
                            <div><p class="text-gray-500 dark:text-gray-400">From</p>
                                <a href="{{ route('warehouses.show', $transfer->from_warehouse_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $transfer->fromWarehouse?->name }}</a></div>
                            <div><p class="text-gray-500 dark:text-gray-400">To</p>
                                <a href="{{ route('warehouses.show', $transfer->to_warehouse_id) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $transfer->toWarehouse?->name }}</a></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Date sent</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $transfer->transfer_date?->format('M d, Y') }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Reference</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $transfer->reference ?: '—' }}</p></div>
                            <div><p class="text-gray-500 dark:text-gray-400">Date received</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $transfer->received_date?->format('M d, Y') ?? '—' }}</p></div>
                            @if($transfer->cancelled_at)
                                <div><p class="text-gray-500 dark:text-gray-400">Cancelled</p><p class="font-medium text-gray-900 dark:text-gray-100">{{ $transfer->cancelled_at->format('M d, Y') }}</p></div>
                            @endif
                        </div>
                    </x-card>

                    <x-card>
                        <div class="p-4 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Goods</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead><tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                        <th class="px-3 py-2 text-left">Item</th>
                                        <th class="px-3 py-2 text-right">Sent</th>
                                        @if($done)
                                            <th class="px-3 py-2 text-right">Received</th>
                                            <th class="px-3 py-2 text-right hidden sm:table-cell">Back</th>
                                            <th class="px-3 py-2 text-right hidden sm:table-cell">Lost</th>
                                        @endif
                                        @unless($transfer->isDraft())
                                            <th class="px-3 py-2 text-right hidden sm:table-cell">Cost</th>
                                        @endunless
                                    </tr></thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                        @foreach($transfer->items as $line)
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <a href="{{ route('inventory.show', $line->item_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $line->item?->name }}</a>
                                                    @if($line->item?->sku)<span class="block text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $line->item->sku }}</span>@endif
                                                    @unless($transfer->isDraft())
                                                        <span class="sm:hidden block text-xs text-gray-500 dark:text-gray-400">Cost @money($line->shipped_cost)</span>
                                                    @endunless
                                                    @if($done && ((float) $line->quantity_returned > 0 || (float) $line->quantity_lost > 0))
                                                        <span class="sm:hidden block text-xs text-gray-500 dark:text-gray-400">
                                                            @if((float) $line->quantity_returned > 0)Back: {{ $fmt($line->quantity_returned) }} @endif
                                                            @if((float) $line->quantity_lost > 0)Lost: {{ $fmt($line->quantity_lost) }}@endif
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-right">{{ $fmt($line->quantity) }}</td>
                                                @if($done)
                                                    <td class="px-3 py-2 text-right font-medium">{{ $fmt($line->quantity_received) }}</td>
                                                    <td class="px-3 py-2 text-right hidden sm:table-cell">{{ $fmt($line->quantity_returned) }}</td>
                                                    <td class="px-3 py-2 text-right hidden sm:table-cell {{ (float) $line->quantity_lost > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $fmt($line->quantity_lost) }}</td>
                                                @endif
                                                @unless($transfer->isDraft())
                                                    <td class="px-3 py-2 text-right whitespace-nowrap hidden sm:table-cell">@money($line->shipped_cost)
                                                        <span class="block text-xs text-gray-500 dark:text-gray-400">@money($line->unitCost()) each</span>
                                                    </td>
                                                @endunless
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @unless($transfer->isDraft())
                                <dl class="mt-3 border-t border-gray-200 dark:border-gray-700 pt-3 space-y-1 text-sm font-semibold">
                                    <div class="flex justify-between gap-4 text-gray-900 dark:text-gray-100"><dt>Cost of goods sent</dt><dd class="whitespace-nowrap">@money($transfer->shippedCost())</dd></div>
                                    @if((float) $transfer->items->sum('lost_cost') > 0)
                                        <div class="flex justify-between gap-4 text-red-700 dark:text-red-400"><dt>Lost on the way</dt><dd class="whitespace-nowrap">@money($transfer->items->sum('lost_cost'))</dd></div>
                                    @endif
                                </dl>
                            @endunless
                            <p class="form-help mt-4">
                                @switch($status)
                                    @case('draft') Nothing has moved yet. @break
                                    @case('in_transit') The goods have left {{ $transfer->fromWarehouse?->name }} and are on the road. They are still your stock (shown as "in transit") until you receive them. @break
                                    @case('received') The goods are in {{ $transfer->toWarehouse?->name }} at the cost they left {{ $transfer->fromWarehouse?->name }} at. To undo this, transfer them back. @break
                                    @default The goods went back to {{ $transfer->fromWarehouse?->name }} at the cost they left at.
                                @endswitch
                            </p>
                        </div>
                    </x-card>

                    @if($transfer->notes)
                        <x-card>
                            <div class="p-4 sm:p-6 text-sm text-gray-700 dark:text-gray-300">
                                <p class="font-medium text-gray-900 dark:text-gray-100">Notes</p>
                                <p class="whitespace-pre-line">{{ $transfer->notes }}</p>
                            </div>
                        </x-card>
                    @endif
                </div>

                <div class="space-y-6">
                    @if($transfer->isDraft())
                        <x-card>
                            <div class="p-4 sm:p-6 space-y-3">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Next step</h3>
                                <form method="POST" action="{{ route('stock-transfers.transfer-now', $transfer) }}">@csrf
                                    <button type="submit" class="btn-primary w-full">Transfer now</button>
                                </form>
                                <form method="POST" action="{{ route('stock-transfers.ship', $transfer) }}">@csrf
                                    <button type="submit" class="{{ $secondary }} w-full">Ship only (goods on the road)</button>
                                </form>
                                <form method="POST" action="{{ route('stock-transfers.destroy', $transfer) }}" data-confirm="Delete this draft transfer?">@csrf @method('DELETE')
                                    <button type="submit" class="{{ $btn }} w-full bg-white dark:bg-gray-800 border-red-300 text-red-700 dark:text-red-400 hover:bg-red-50">Delete</button>
                                </form>
                            </div>
                        </x-card>
                    @elseif($transfer->isInTransit())
                        <x-card>
                            <div class="p-4 sm:p-6 space-y-3">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Next step</h3>
                                <button type="button" class="btn-primary w-full" data-open-modal="receive-transfer">Receive goods</button>
                                <form method="POST" action="{{ route('stock-transfers.cancel', $transfer) }}" data-confirm="Cancel this transfer? The goods go back to {{ $transfer->fromWarehouse?->name }}.">@csrf
                                    <button type="submit" class="{{ $secondary }} w-full">Cancel transfer</button>
                                </form>
                            </div>
                        </x-card>
                    @else
                        <x-card>
                            <div class="p-4 sm:p-6 space-y-3 text-sm">
                                <p class="text-gray-600 dark:text-gray-400">This transfer is {{ $status === 'received' ? 'received' : 'cancelled' }} and can't be changed.</p>
                                @if($status === 'received')
                                    <a href="{{ route('stock-transfers.create', ['from' => $transfer->to_warehouse_id, 'to' => $transfer->from_warehouse_id]) }}" class="{{ $secondary }} w-full">Transfer goods back</a>
                                @endif
                            </div>
                        </x-card>
                    @endif

                    @if($lossJournal)
                        <x-card>
                            <div class="p-4 sm:p-6 text-sm space-y-1">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Loss posted</h3>
                                <p class="text-gray-600 dark:text-gray-400">Stock Losses @money($lossJournal->total_debit) (debit), Inventory (credit).</p>
                                @can('view journals')
                                    <a href="{{ route('journals.show', $lossJournal) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Journal {{ $lossJournal->journal_number }}</a>
                                @else
                                    <p class="text-gray-900 dark:text-gray-100">Journal {{ $lossJournal->journal_number }}</p>
                                @endcan
                            </div>
                        </x-card>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($transfer->isInTransit())
        <x-modal name="receive-transfer" title="Receive transfer {{ $transfer->transfer_number }}" maxWidth="lg" :show="$errors->has('received_date') || $errors->has('shortfall') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'items.'))">
            <form method="POST" action="{{ route('stock-transfers.receive', $transfer) }}" class="p-6 space-y-4">
                @csrf
                <x-lock-date-notice field="received_date" />
                <x-field name="received_date" label="Date received" type="date" required :value="old('received_date', max(now()->toDateString(), $transfer->transfer_date->toDateString()))" />
                <div>
                    <p class="form-label">How many arrived?</p>
                    <div class="space-y-2">
                        @foreach($transfer->items as $i => $line)
                            <div class="flex items-center justify-between gap-3">
                                <label for="recv-{{ $line->id }}" class="text-sm text-gray-700 dark:text-gray-300">{{ $line->item?->name }} <span class="text-gray-500 dark:text-gray-400">(sent {{ $fmt($line->quantity) }})</span></label>
                                <input type="hidden" name="items[{{ $i }}][id]" value="{{ $line->id }}">
                                <input type="number" id="recv-{{ $line->id }}" name="items[{{ $i }}][quantity_received]" value="{{ old("items.$i.quantity_received", (float) $line->quantity) }}"
                                       min="0" max="{{ (float) $line->quantity }}" step="any" class="form-control text-sm w-28">
                            </div>
                            @error("items.$i.quantity_received")<p class="form-error">{{ $message }}</p>@enderror
                        @endforeach
                    </div>
                </div>
                <fieldset>
                    <legend class="form-label">If fewer arrived than were sent</legend>
                    <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" name="shortfall" value="return" class="mt-1" @checked(old('shortfall', 'return') === 'return')>
                        <span>They are going back to {{ $transfer->fromWarehouse?->name }}</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300 mt-1">
                        <input type="radio" name="shortfall" value="lost" class="mt-1" @checked(old('shortfall') === 'lost')>
                        <span>They were lost or damaged: write them off to Stock Losses at cost</span>
                    </label>
                </fieldset>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button type="button" class="{{ $secondary }}" data-close-modal="receive-transfer">Close</button>
                    <button type="submit" class="btn-primary">Receive</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
