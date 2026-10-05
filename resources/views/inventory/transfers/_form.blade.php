{{--
    Stock transfer form (session 13): date, from and to warehouse, and the
    items to send. Each line shows how many are free in the source
    warehouse. $transfer, $warehouses, $lines.
--}}
@php
    $startLines = collect(old('items', $lines))->values()->map(fn ($l) => [
        'item_id' => (string) ($l['item_id'] ?? ''),
        'itemSearch' => (string) ($l['itemSearch'] ?? ''),
        'quantity' => $l['quantity'] ?? 1,
        'free' => $l['free'] ?? null,
    ])->all();
    $fromId = (string) old('from_warehouse_id', $transfer->from_warehouse_id);
    $toId = (string) old('to_warehouse_id', $transfer->to_warehouse_id);
@endphp

<div x-data="transferLines({ lines: @js($startLines), from: @js($fromId), url: @js(route('stock-transfers.items')), warehouses: @js($warehouses->pluck('name', 'id')) })" class="space-y-6">
    <x-card>
        <div class="p-4 sm:p-6">
            <x-lock-date-notice field="transfer_date" />
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <x-field name="transfer_date" label="Date" type="date" required :value="old('transfer_date', $transfer->transfer_date?->format('Y-m-d'))" />
                </div>
                <div>
                    <x-field name="from_warehouse_id" label="From" type="select" required x-model="from" @change="refreshFree()">
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($fromId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </x-field>
                </div>
                <div>
                    <x-field name="to_warehouse_id" label="To" type="select" required>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($toId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </x-field>
                </div>
                <div>
                    <x-field name="reference" label="Reference" :value="old('reference', $transfer->reference)" placeholder="Waybill, driver, vehicle..." maxlength="100" />
                </div>
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="p-4 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Items to send</h3>
            @error('items')<p class="form-error mb-2">{{ $message }}</p>@enderror
            <table class="min-w-full line-items">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2">Item</th>
                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Quantity</th>
                        <th class="text-right text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-40">Free in <span x-text="warehouses[from] || 'warehouse'"></span></th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, index) in lines" :key="line.key">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="py-2 pr-2" data-label="Item" data-cell="main">
                                <div class="relative">
                                    <input type="hidden" :name="`items[${index}][item_id]`" :value="line.item_id">
                                    <input type="hidden" :name="`items[${index}][itemSearch]`" :value="line.itemSearch">
                                    <input type="text" role="combobox" aria-label="Item" autocomplete="off" x-model="line.itemSearch"
                                        :aria-expanded="line.open.toString()"
                                        @focus="line.open = true; search(index)" @input="line.open = true; line.item_id = ''" @input.debounce.300ms="search(index)"
                                        @keydown.escape="line.open = false"
                                        @keydown.arrow-down.prevent="line.highlighted = Math.min(line.highlighted + 1, line.results.length - 1)"
                                        @keydown.arrow-up.prevent="line.highlighted = Math.max(line.highlighted - 1, 0)"
                                        @keydown.enter.prevent="pick(index, line.results[line.highlighted])"
                                        placeholder="Search stock items..." class="form-control text-sm">
                                    <div x-show="line.open" x-cloak @click.outside="line.open = false" role="listbox"
                                        class="absolute z-[100] mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 ring-1 ring-black ring-opacity-5 overflow-auto text-sm">
                                        <template x-for="(product, pIndex) in line.results" :key="product.id">
                                            <div role="option" @mousedown.prevent @click="pick(index, product)" @mouseenter="line.highlighted = pIndex"
                                                :class="line.highlighted === pIndex ? 'bg-indigo-600 text-white' : 'text-gray-900 dark:text-gray-100'"
                                                class="cursor-pointer select-none py-2 px-3 flex justify-between gap-2">
                                                <span x-text="product.name"></span>
                                                <span class="text-xs opacity-75 whitespace-nowrap" x-text="qty(product.free) + ' free'"></span>
                                            </div>
                                        </template>
                                        <div x-show="line.results.length === 0" class="py-2 px-3 text-gray-500 dark:text-gray-400">No stock items found</div>
                                    </div>
                                </div>
                                <p class="form-error" x-show="errors[`items.${index}.item_id`]" x-text="errors[`items.${index}.item_id`]"></p>
                            </td>
                            <td class="py-2 pr-2" data-label="Quantity">
                                <input aria-label="Quantity" type="number" :name="`items[${index}][quantity]`" x-model.number="line.quantity" min="0.0001" step="any" class="form-control text-sm"
                                    :class="line.free !== null && Number(line.quantity) > line.free ? 'border-red-500' : ''">
                                <p class="form-error" x-show="errors[`items.${index}.quantity`]" x-text="errors[`items.${index}.quantity`]"></p>
                            </td>
                            <td class="py-2 text-right text-sm" data-label="Free">
                                <span x-show="line.item_id" :class="line.free !== null && Number(line.quantity) > line.free ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300'" x-text="line.free === null ? '…' : qty(line.free)"></span>
                                <span x-show="line.item_id && line.free !== null && Number(line.quantity) > line.free" class="block text-xs text-red-600 dark:text-red-400">Not enough free</span>
                            </td>
                            <td class="py-2 text-center" data-cell="actions">
                                <button type="button" @click="remove(index)" x-show="lines.length > 1" class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm" aria-label="Remove line">Remove</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" @click="add()" class="mt-4 inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                + Add line
            </button>
        </div>
    </x-card>

    <x-card>
        <div class="p-4 sm:p-6">
            <x-field name="notes" label="Notes" type="textarea" rows="2" :value="old('notes', $transfer->notes)" help="Printed on the transfer note that goes with the goods." />
        </div>
    </x-card>

    {{-- Transfer now comes first, so pressing Enter does the default. --}}
    <div class="flex flex-col sm:flex-row-reverse sm:justify-start gap-2">
        <button type="submit" name="action" value="transfer_now" class="btn-primary">Transfer now</button>
        <button type="submit" name="action" value="ship" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-500 rounded-md font-semibold text-xs text-indigo-700 dark:text-indigo-300 uppercase tracking-widest shadow-sm hover:bg-indigo-50 dark:hover:bg-gray-700">Ship only (goods on the road)</button>
        <button type="submit" name="action" value="draft" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">Save as draft</button>
    </div>
    <p class="form-help sm:text-right">"Transfer now" moves the goods straight away. Use "Ship only" when they will take time to arrive, and receive them when they get there.</p>
</div>

@once
    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function transferLines(config) {
            let nextKey = 1;
            const blank = () => ({ key: nextKey++, item_id: '', itemSearch: '', quantity: 1, free: null, open: false, highlighted: 0, results: [], seq: 0 });
            const start = (config.lines || []).map(l => Object.assign(blank(), l, { key: nextKey++ }));
            return {
                lines: start.length ? start : [blank()],
                from: config.from,
                warehouses: config.warehouses || {},
                errors: @js(isset($errors) ? collect($errors->getMessages())->map(fn ($m) => $m[0])->all() : []),
                init() { if (this.lines.some(l => l.item_id && l.free === null)) this.refreshFree(); },
                async get(params) {
                    const response = await fetch(config.url + '?' + new URLSearchParams(params), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
                    });
                    if (!response.ok) return [];
                    return (await response.json()).data || [];
                },
                add() { this.lines.push(blank()); },
                remove(index) { this.lines.splice(index, 1); },
                search(index) {
                    const line = this.lines[index];
                    if (!line) return;
                    const seq = ++line.seq;
                    this.get({ q: line.itemSearch || '', warehouse_id: this.from }).then(rows => {
                        if (line.seq !== seq) return;
                        line.results = rows.map(p => ({ id: String(p.id), name: p.name, free: Number(p.free) }));
                        line.highlighted = 0;
                    });
                },
                pick(index, product) {
                    if (!product) return;
                    const line = this.lines[index];
                    line.item_id = product.id;
                    line.itemSearch = product.name;
                    line.free = product.free;
                    line.open = false;
                },
                refreshFree() {
                    const ids = this.lines.filter(l => l.item_id).map(l => l.item_id);
                    if (!ids.length) return;
                    this.lines.forEach(l => { if (l.item_id) l.free = null; });
                    this.get({ ids: ids.join(','), warehouse_id: this.from }).then(rows => {
                        const free = {};
                        rows.forEach(r => { free[String(r.id)] = Number(r.free); });
                        this.lines.forEach(l => { if (l.item_id) l.free = free[l.item_id] ?? 0; });
                    });
                },
                qty(value) { return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 }); },
            };
        }
    </script>
    @endpush
@endonce
