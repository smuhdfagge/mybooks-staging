{{--
    Assembly order form (session 14): which bill, how many, the date and
    the warehouses. Shows how much of each component is needed and free in
    the chosen warehouse, and the most that can be made right now.
    $order, $boms.
--}}
@php
    $breakdown = $order->kind === 'breakdown';
    $bomId = (string) old('bill_of_materials_id', $order->bill_of_materials_id);
    $quantity = old('quantity', $order->planned_quantity !== null ? rtrim(rtrim(number_format((float) $order->planned_quantity, 4, '.', ''), '0'), '.') : '');
    $fromId = old('warehouse_id', $order->warehouse_id);
@endphp

<div x-data="assemblyPlan({ url: @js(route('assembly-orders.availability')), bom: @js($bomId), quantity: @js($quantity), kind: @js($order->kind) })" class="space-y-6">
    <input type="hidden" name="kind" value="{{ $order->kind }}">
    <x-card>
        <div class="p-4 sm:p-6">
            <x-lock-date-notice field="assembly_date" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <x-field name="bill_of_materials_id" :label="$breakdown ? 'What to break down' : 'What to make'" type="select" required x-model="bom" @change="load()">
                        <option value="">Choose a bill of materials...</option>
                        @foreach($boms as $b)
                            <option value="{{ $b->id }}" @selected($bomId === (string) $b->id)>{{ $b->item?->name }} — {{ $b->label() }}</option>
                        @endforeach
                    </x-field>
                </div>
                <div>
                    <x-field name="quantity" :label="$breakdown ? 'How many to break down' : 'How many to make'" type="number" step="any" min="0.0001" required :value="$quantity" x-model.number="quantity" inputmode="decimal" />
                    <p class="form-help" x-show="info && !breakdown" x-cloak>
                        <span x-text="batches()"></span>
                    </p>
                </div>
                <div>
                    <x-field name="assembly_date" label="Date" type="date" required :value="old('assembly_date', $order->assembly_date?->format('Y-m-d'))" />
                </div>
                <x-warehouse-picker name="warehouse_id" :label="$breakdown ? 'Take the items from' : 'Take components from'" :selected="$fromId" x-model="warehouse" @change="load()" />
                <x-warehouse-picker name="to_warehouse_id" :label="$breakdown ? 'Put the parts in' : 'Put finished goods in'" :selected="old('to_warehouse_id', $order->to_warehouse_id ?? $fromId)" />
            </div>
        </div>
    </x-card>

    <x-card x-show="info" x-cloak>
        <div class="p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 mb-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $breakdown ? 'In stock' : 'Components needed' }}</h3>
                <p class="text-sm" :class="short() ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-600 dark:text-gray-400'">
                    <span x-text="maxText()"></span>
                </p>
            </div>
            @error('items')<p class="form-error mb-2">{{ $message }}</p>@enderror
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead><tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="py-2 pr-2 text-left">Item</th>
                        <th class="py-2 px-2 text-right">Needed</th>
                        <th class="py-2 pl-2 text-right">Free</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                        <template x-for="(c, i) in (info ? info.components : [])" :key="i">
                            <tr>
                                <td class="py-2 pr-2" x-text="c.name"></td>
                                <td class="py-2 px-2 text-right whitespace-nowrap" x-text="qty(c.per_unit * (Number(quantity) || 0)) + (c.unit ? ' ' + c.unit : '')"></td>
                                <td class="py-2 pl-2 text-right whitespace-nowrap" :class="c.per_unit * (Number(quantity) || 0) > c.free + 0.00001 ? 'text-red-600 dark:text-red-300 font-medium' : ''">
                                    <span x-text="qty(c.free) + (c.unit ? ' ' + c.unit : '')"></span>
                                    <span x-show="c.per_unit * (Number(quantity) || 0) > c.free + 0.00001" class="block text-xs">Not enough</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            @unless($breakdown)
                <p class="mt-3 text-sm text-gray-700 dark:text-gray-300" x-show="info">
                    Estimated cost: <strong x-text="money(info ? info.unit_cost : 0)"></strong> each,
                    <strong x-text="money((info ? info.unit_cost : 0) * (Number(quantity) || 0))"></strong> in all, at today's component costs. The real cost is worked out when the build is done.
                </p>
            @endunless
        </div>
    </x-card>

    <x-card>
        <div class="p-4 sm:p-6">
            <x-field name="notes" label="Notes" type="textarea" rows="2" :value="old('notes', $order->notes)" help="Printed on the production sheet." />
        </div>
    </x-card>

    {{-- The main action comes first, so pressing Enter does it. --}}
    <div class="flex flex-col sm:flex-row-reverse sm:justify-start gap-2">
        <button type="submit" name="action" value="complete" class="btn-primary">{{ $breakdown ? 'Break down now' : 'Build now' }}</button>
        <button type="submit" name="action" value="draft" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">Save as draft</button>
    </div>
    <p class="form-help sm:text-right">{{ $breakdown ? '"Break down now" takes the items out of stock and puts the parts back in.' : '"Build now" uses the components and adds the finished goods to stock straight away, as planned. Save as a draft if you want to record what was really used and made afterwards.' }}</p>
</div>

@once
    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function assemblyPlan(config) {
            return {
                bom: config.bom || '',
                quantity: config.quantity,
                warehouse: '',
                breakdown: config.kind === 'breakdown',
                info: null,
                init() {
                    const picker = document.getElementById('warehouse_id');
                    this.warehouse = picker ? picker.value : '';
                    this.load();
                },
                async load() {
                    if (!this.bom) { this.info = null; return; }
                    const params = new URLSearchParams({ bill_of_materials_id: this.bom, warehouse_id: this.warehouse || '', kind: config.kind || 'build' });
                    const response = await fetch(config.url + '?' + params, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
                    });
                    this.info = response.ok ? await response.json() : null;
                    if (this.info && (this.quantity === '' || this.quantity === null)) this.quantity = this.info.output_quantity;
                },
                batches() {
                    if (!this.info || !this.info.output_quantity) return '';
                    const b = (Number(this.quantity) || 0) / this.info.output_quantity;
                    return '= ' + this.qty(b) + (b === 1 ? ' batch' : ' batches') + ' (one batch makes ' + this.qty(this.info.output_quantity) + ')';
                },
                short() {
                    return !!this.info && (Number(this.quantity) || 0) > this.info.max + 0.00001;
                },
                maxText() {
                    if (!this.info) return '';
                    if (this.breakdown) return this.qty(this.info.max) + ' free to break down';
                    return 'You can make up to ' + this.qty(this.info.max) + ' now';
                },
                qty(value) { return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 4 }); },
                money(value) { return '{{ \App\Support\Money::symbol() }}' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
    @endpush
@endonce
