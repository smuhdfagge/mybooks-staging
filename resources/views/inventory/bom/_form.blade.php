{{--
    Bill of materials form (session 14): the item made, how many a batch
    makes, the components per batch (with wastage %), and extra costs per
    batch with the account each comes from. Shows the estimated cost.
    $bom, $finished, $components, $costs, $accounts, $defaultAccountId.
--}}
@php
    $startComponents = collect(old('components', $components))->values()->map(fn ($l) => [
        'item_id' => (string) ($l['item_id'] ?? ''),
        'itemSearch' => (string) ($l['itemSearch'] ?? ''),
        'unit' => (string) ($l['unit'] ?? ''),
        'cost' => (float) ($l['cost'] ?? 0),
        'quantity' => $l['quantity'] ?? '',
        'waste_percentage' => $l['waste_percentage'] ?? 0,
    ])->all();
    $startCosts = collect(old('costs', $costs))->values()->map(fn ($c) => [
        'description' => (string) ($c['description'] ?? ''),
        'amount' => $c['amount'] ?? '',
        'account_id' => (string) ($c['account_id'] ?? ''),
    ])->all();
    $finishedStart = [
        'id' => (string) old('item_id', $bom->item_id),
        'name' => old('item_search', $finished?->name ?? ''),
        'unit' => $finished?->unit ?? '',
    ];
    $active = (bool) old('is_active', $bom->exists ? $bom->is_active : true);
@endphp

<div x-data="bomForm({ url: @js(route('bill-of-materials.items')), finished: @js($finishedStart), components: @js($startComponents), costs: @js($startCosts), output: @js(old('output_quantity', (float) $bom->output_quantity ?: 1)), defaultAccount: @js((string) $defaultAccountId) })" class="space-y-6">
    <x-card>
        <div class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label for="finished-search" class="form-label">Item this makes <span class="text-red-600 dark:text-red-300">*</span></label>
                <div class="relative">
                    <input type="hidden" name="item_id" :value="finished.id">
                    <input type="hidden" name="item_search" :value="finished.name">
                    <input type="text" id="finished-search" role="combobox" autocomplete="off" x-model="finished.name" :aria-expanded="finished.open ? 'true' : 'false'"
                        @focus="finished.open = true; search(finished)" @input="finished.open = true; finished.id = ''" @input.debounce.300ms="search(finished)"
                        @keydown.escape="finished.open = false"
                        @keydown.arrow-down.prevent="finished.highlighted = Math.min(finished.highlighted + 1, finished.results.length - 1)"
                        @keydown.arrow-up.prevent="finished.highlighted = Math.max(finished.highlighted - 1, 0)"
                        @keydown.enter.prevent="pickFinished(finished.results[finished.highlighted])"
                        placeholder="Search stock items, e.g. Layer feed 25kg bag" class="form-control {{ $errors->has('item_id') ? 'border-red-500' : '' }}">
                    <div x-show="finished.open" x-cloak @click.outside="finished.open = false" role="listbox"
                        class="absolute z-[100] mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 ring-1 ring-black ring-opacity-5 overflow-auto text-sm">
                        <template x-for="(product, pIndex) in finished.results" :key="product.id">
                            <div role="option" @mousedown.prevent @click="pickFinished(product)" @mouseenter="finished.highlighted = pIndex"
                                :class="finished.highlighted === pIndex ? 'bg-brand-600 text-white' : 'text-gray-900 dark:text-gray-100'"
                                class="cursor-pointer select-none py-2 px-3" x-text="product.name"></div>
                        </template>
                        <div x-show="finished.results.length === 0" class="py-2 px-3 text-gray-500 dark:text-gray-400">No stock items found</div>
                    </div>
                </div>
                <p class="form-help">Only items that keep stock can be made. Make the item first if it isn't there.</p>
                @error('item_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <x-field name="name" label="Name" required :value="old('name', $bom->name)" placeholder="e.g. Layer feed" maxlength="255" />
            </div>
            <div>
                <x-field name="version" label="Version" :value="old('version', $bom->version)" placeholder="e.g. 2026 recipe" maxlength="50" help="Optional. Use it when an item has more than one recipe." />
            </div>
            <div>
                <label for="output_quantity" class="form-label">One batch makes <span class="text-red-600 dark:text-red-300">*</span></label>
                <div class="flex items-center gap-2">
                    <input type="number" id="output_quantity" name="output_quantity" x-model.number="output" min="0.0001" step="any" inputmode="decimal" required class="form-control {{ $errors->has('output_quantity') ? 'border-red-500' : '' }}">
                    <span class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap" x-text="finished.unit"></span>
                </div>
                <p class="form-help">For example 40 (bags). Component quantities below are for one batch.</p>
                @error('output_quantity')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-end pb-2">
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_active" value="1" @checked($active) class="rounded border-gray-300 dark:border-gray-600 text-brand-600 dark:text-brand-300">
                    In use (can be built from)
                </label>
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="p-4 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Components per batch</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Stock items used up. Wastage % adds to what is taken from stock (spillage, offcuts); it is part of the product's cost.</p>
            @error('components')<p class="form-error mb-2">{{ $message }}</p>@enderror
            <table class="min-w-full line-items">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2">Item</th>
                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Quantity</th>
                        <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-24">Wastage %</th>
                        <th class="text-right text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Est. cost</th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, index) in lines" :key="line.key">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="py-2 pr-2" data-label="Item" data-cell="main">
                                <div class="relative">
                                    <input type="hidden" :name="`components[${index}][item_id]`" :value="line.item_id">
                                    <input type="hidden" :name="`components[${index}][itemSearch]`" :value="line.itemSearch">
                                    <input type="hidden" :name="`components[${index}][unit]`" :value="line.unit">
                                    <input type="hidden" :name="`components[${index}][cost]`" :value="line.cost">
                                    <input type="text" role="combobox" aria-label="Component" autocomplete="off" x-model="line.itemSearch"
                                        :aria-expanded="line.open ? 'true' : 'false'"
                                        @focus="line.open = true; search(line)" @input="line.open = true; line.item_id = ''" @input.debounce.300ms="search(line)"
                                        @keydown.escape="line.open = false"
                                        @keydown.arrow-down.prevent="line.highlighted = Math.min(line.highlighted + 1, line.results.length - 1)"
                                        @keydown.arrow-up.prevent="line.highlighted = Math.max(line.highlighted - 1, 0)"
                                        @keydown.enter.prevent="pick(line, line.results[line.highlighted])"
                                        placeholder="Search stock items..." class="form-control text-sm">
                                    <div x-show="line.open" x-cloak @click.outside="line.open = false" role="listbox"
                                        class="absolute z-[100] mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 ring-1 ring-black ring-opacity-5 overflow-auto text-sm">
                                        <template x-for="(product, pIndex) in line.results" :key="product.id">
                                            <div role="option" @mousedown.prevent @click="pick(line, product)" @mouseenter="line.highlighted = pIndex"
                                                :class="line.highlighted === pIndex ? 'bg-brand-600 text-white' : 'text-gray-900 dark:text-gray-100'"
                                                class="cursor-pointer select-none py-2 px-3" x-text="product.name"></div>
                                        </template>
                                        <div x-show="line.results.length === 0" class="py-2 px-3 text-gray-500 dark:text-gray-400">No stock items found</div>
                                    </div>
                                </div>
                                <p class="form-error" x-show="errors[`components.${index}.item_id`]" x-text="errors[`components.${index}.item_id`]"></p>
                            </td>
                            <td class="py-2 pr-2" data-label="Quantity">
                                <div class="flex items-center gap-1">
                                    <input aria-label="Quantity per batch" type="number" :name="`components[${index}][quantity]`" x-model.number="line.quantity" min="0" step="any" inputmode="decimal" class="form-control text-sm">
                                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="line.unit"></span>
                                </div>
                                <p class="form-error" x-show="errors[`components.${index}.quantity`]" x-text="errors[`components.${index}.quantity`]"></p>
                            </td>
                            <td class="py-2 pr-2" data-label="Wastage %">
                                <input aria-label="Wastage percent" type="number" :name="`components[${index}][waste_percentage]`" x-model.number="line.waste_percentage" min="0" max="100" step="any" inputmode="decimal" class="form-control text-sm">
                                <p class="form-error" x-show="errors[`components.${index}.waste_percentage`]" x-text="errors[`components.${index}.waste_percentage`]"></p>
                            </td>
                            <td class="py-2 text-right text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap" data-label="Est. cost" x-text="money(lineCost(line))"></td>
                            <td class="py-2 text-center" data-cell="actions">
                                <button type="button" @click="lines.splice(index, 1)" x-show="lines.length > 1" class="text-red-600 hover:text-red-800 dark:text-red-300 text-sm" aria-label="Remove component">Remove</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" @click="lines.push(blankLine())" class="mt-4 inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">+ Add component</button>
        </div>
    </x-card>

    <x-card>
        <div class="p-4 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Extra costs per batch</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Optional. Labour, power, packaging and other costs that aren't kept as stock. They are added to the finished goods' cost: Inventory goes up and the account you choose is credited (for example Production Costs Applied, Accrued Salaries, or a bank account if you pay as you go).</p>
            <div class="space-y-3">
                <template x-for="(cost, index) in costs" :key="cost.key">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-start border-b border-gray-200 dark:border-gray-700 pb-3">
                        <div class="sm:col-span-4">
                            <label class="sr-only" :for="`cost-desc-${index}`">Description</label>
                            <input type="text" :id="`cost-desc-${index}`" :name="`costs[${index}][description]`" x-model="cost.description" placeholder="e.g. Labour" maxlength="255" class="form-control text-sm">
                            <p class="form-error" x-show="errors[`costs.${index}.description`]" x-text="errors[`costs.${index}.description`]"></p>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="sr-only" :for="`cost-amount-${index}`">Amount per batch</label>
                            <input type="number" :id="`cost-amount-${index}`" :name="`costs[${index}][amount]`" x-model.number="cost.amount" min="0" step="0.01" inputmode="decimal" placeholder="Amount per batch" class="form-control text-sm">
                            <p class="form-error" x-show="errors[`costs.${index}.amount`]" x-text="errors[`costs.${index}.amount`]"></p>
                        </div>
                        <div class="sm:col-span-4">
                            <label class="sr-only" :for="`cost-account-${index}`">Account to credit</label>
                            <select :id="`cost-account-${index}`" :name="`costs[${index}][account_id]`" x-model="cost.account_id" class="form-control text-sm">
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_code }} {{ $account->name }}</option>
                                @endforeach
                            </select>
                            <p class="form-error" x-show="errors[`costs.${index}.account_id`]" x-text="errors[`costs.${index}.account_id`]"></p>
                        </div>
                        <div class="sm:col-span-1 text-right">
                            <button type="button" @click="costs.splice(index, 1)" class="text-red-600 hover:text-red-800 dark:text-red-300 text-sm py-2" aria-label="Remove cost">Remove</button>
                        </div>
                    </div>
                </template>
            </div>
            <button type="button" @click="costs.push(blankCost())" class="mt-4 inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">+ Add extra cost</button>
        </div>
    </x-card>

    <x-card>
        <div class="p-4 sm:p-6">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between gap-4 text-gray-700 dark:text-gray-300"><dt>Components, one batch</dt><dd class="whitespace-nowrap" x-text="money(componentsTotal())"></dd></div>
                <div class="flex justify-between gap-4 text-gray-700 dark:text-gray-300"><dt>Extra costs, one batch</dt><dd class="whitespace-nowrap" x-text="money(extraTotal())"></dd></div>
                <div class="flex justify-between gap-4 font-semibold text-gray-900 dark:text-gray-100 border-t border-gray-200 dark:border-gray-700 pt-2"><dt>Estimated cost of each <span x-text="finished.unit || 'unit'"></span></dt><dd class="whitespace-nowrap" x-text="money(perUnit())"></dd></div>
            </dl>
            <p class="form-help mt-2">At today's component costs (average cost of what's in stock). The real cost is worked out on each build.</p>
            <div class="mt-4">
                <x-field name="description" label="Notes" type="textarea" rows="2" :value="old('description', $bom->description)" help="Mixing steps, checks, anything the person making it should know." />
            </div>
        </div>
    </x-card>

    <div class="flex flex-col sm:flex-row-reverse sm:justify-start gap-2">
        <button type="submit" class="btn-primary">Save bill of materials</button>
    </div>
</div>

@once
    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function bomForm(config) {
            let nextKey = 1;
            const blankLine = () => ({ key: nextKey++, item_id: '', itemSearch: '', unit: '', cost: 0, quantity: '', waste_percentage: 0, open: false, highlighted: 0, results: [], seq: 0 });
            const blankCost = () => ({ key: nextKey++, description: '', amount: '', account_id: config.defaultAccount || '' });
            const lines = (config.components || []).map(l => Object.assign(blankLine(), l, { key: nextKey++ }));
            const costs = (config.costs || []).map(c => Object.assign(blankCost(), c, { key: nextKey++, account_id: c.account_id || config.defaultAccount || '' }));
            return {
                finished: Object.assign({ open: false, highlighted: 0, results: [], seq: 0 }, config.finished || {}),
                lines: lines.length ? lines : [blankLine()],
                costs,
                output: config.output,
                errors: @js(isset($errors) ? collect($errors->getMessages())->map(fn ($m) => $m[0])->all() : []),
                blankLine,
                blankCost,
                async search(target) {
                    const seq = ++target.seq;
                    const response = await fetch(config.url + '?' + new URLSearchParams({ q: target === this.finished ? target.name : (target.itemSearch || '') }), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
                    });
                    const rows = response.ok ? ((await response.json()).data || []) : [];
                    if (target.seq !== seq) return;
                    target.results = rows.map(p => ({ id: String(p.id), name: p.name, unit: p.unit || '', cost: Number(p.cost) || 0 }));
                    target.highlighted = 0;
                },
                pickFinished(product) {
                    if (!product) return;
                    Object.assign(this.finished, { id: product.id, name: product.name, unit: product.unit, open: false });
                    const name = document.getElementById('name');
                    if (name && !name.value) name.value = product.name;
                },
                pick(line, product) {
                    if (!product) return;
                    Object.assign(line, { item_id: product.id, itemSearch: product.name, unit: product.unit, cost: product.cost, open: false });
                },
                lineCost(line) { return (Number(line.quantity) || 0) * (1 + (Number(line.waste_percentage) || 0) / 100) * (Number(line.cost) || 0); },
                componentsTotal() { return this.lines.reduce((sum, l) => sum + this.lineCost(l), 0); },
                extraTotal() { return this.costs.reduce((sum, c) => sum + (Number(c.amount) || 0), 0); },
                perUnit() { const out = Number(this.output) || 0; return out > 0 ? (this.componentsTotal() + this.extraTotal()) / out : 0; },
                money(value) { return '{{ \App\Support\Money::symbol() }}' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
    @endpush
@endonce
