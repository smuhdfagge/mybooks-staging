{{--
    Line items and totals for sales-style documents (quotations, credit
    notes), laid out like the invoice form. The figures shown follow the
    server's rules (DocumentTotals): VAT is charged after discounts. The
    server works the totals out again when saving.

    <x-sales-lines :lines="$lines" :discount-type="..." :discount-value="..." />

    lines: [['item_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'discount', 'item_name'], ...]
    Set :document-discount="false" for documents without a document discount.
--}}
@props([
    'lines' => [],
    'discountType' => '',
    'discountValue' => 0,
    'documentDiscount' => true,
    'title' => 'Items',
    'summaryTitle' => 'Summary',
])

@php
    $startLines = collect($lines)->values()->map(fn ($l) => [
        'item_id' => (string) ($l['item_id'] ?? ''),
        'itemSearch' => (string) ($l['item_name'] ?? ''),
        'description' => (string) ($l['description'] ?? ''),
        'quantity' => (float) ($l['quantity'] ?? 1),
        'unit_price' => (float) ($l['unit_price'] ?? 0),
        'tax_rate' => (float) ($l['tax_rate'] ?? 0),
        'discount' => (float) ($l['discount'] ?? 0),
    ])->all();
@endphp

<div x-data="salesLines({ lines: @js($startLines), discountType: @js((string) $discountType), discountValue: @js((float) $discountValue), productsUrl: @js(route('lookup.items')) })" class="space-y-6">
    <x-card>
        <div class="p-4 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $title }}</h3>
            @error('items')<p class="form-error mb-2">{{ $message }}</p>@enderror
            <div class="overflow-visible">
                <table class="min-w-full line-items">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-1/3">Description</th>
                            <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-24">Qty</th>
                            <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Price</th>
                            <th class="text-left text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-24">VAT %</th>
                            <th class="text-right text-sm font-medium text-gray-700 dark:text-gray-300 pb-2 w-32">Total</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, index) in lines" :key="line.key">
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <td class="py-2 pr-2" data-label="Description" data-cell="main">
                                    <div class="relative mb-1">
                                        <input type="hidden" :name="`items[${index}][item_id]`" :value="line.item_id">
                                        <input type="hidden" :name="`items[${index}][discount]`" :value="line.discount">
                                        <input type="hidden" :name="`items[${index}][discount_type]`" value="fixed">
                                        <input aria-label="Item" type="text" x-model="line.itemSearch"
                                            @focus="line.open = true; search(index)" @input="line.open = true" @input.debounce.300ms="search(index)"
                                            @keydown.escape="line.open = false"
                                            @keydown.arrow-down.prevent="line.highlighted = Math.min(line.highlighted + 1, (line.results || []).length - 1)"
                                            @keydown.arrow-up.prevent="line.highlighted = Math.max(line.highlighted - 1, 0)"
                                            @keydown.enter.prevent="pick(index, (line.results || [])[line.highlighted])"
                                            placeholder="Search items (optional)..." autocomplete="off" class="form-control text-sm">
                                        <div x-show="line.open" x-cloak @click.outside="line.open = false"
                                            class="absolute z-[100] mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 ring-1 ring-black ring-opacity-5 overflow-auto text-sm">
                                            <template x-for="(product, pIndex) in (line.results || [])" :key="product.id">
                                                <div @mousedown.prevent @click="pick(index, product)" @mouseenter="line.highlighted = pIndex"
                                                    :class="line.highlighted === pIndex ? 'bg-indigo-600 text-white' : 'text-gray-900 dark:text-gray-100'"
                                                    class="cursor-pointer select-none py-2 px-3" x-text="product.name"></div>
                                            </template>
                                            <div x-show="(line.results || []).length === 0" class="py-2 px-3 text-gray-500 dark:text-gray-400">No items found</div>
                                        </div>
                                    </div>
                                    <input aria-label="Description" type="text" :name="`items[${index}][description]`" x-model="line.description" required placeholder="Description" class="form-control text-sm">
                                    <p class="form-error" x-show="errors[`items.${index}.description`]" x-text="errors[`items.${index}.description`]"></p>
                                </td>
                                <td class="py-2 pr-2" data-label="Qty">
                                    <input aria-label="Quantity" type="number" :name="`items[${index}][quantity]`" x-model.number="line.quantity" min="0.01" step="0.01" required class="form-control text-sm">
                                    <p class="form-error" x-show="errors[`items.${index}.quantity`]" x-text="errors[`items.${index}.quantity`]"></p>
                                </td>
                                <td class="py-2 pr-2" data-label="Price">
                                    <input aria-label="Unit price" type="number" :name="`items[${index}][unit_price]`" x-model.number="line.unit_price" min="0" step="0.01" required class="form-control text-sm">
                                </td>
                                <td class="py-2 pr-2" data-label="VAT %">
                                    <input aria-label="VAT rate (%)" type="number" :name="`items[${index}][tax_rate]`" x-model.number="line.tax_rate" min="0" max="100" step="0.01" class="form-control text-sm">
                                    <p class="form-error" x-show="errors[`items.${index}.tax_rate`]" x-text="errors[`items.${index}.tax_rate`]"></p>
                                </td>
                                <td class="py-2 text-right text-sm font-medium text-gray-900 dark:text-gray-100" data-label="Total" x-text="money(lineTotal(line))"></td>
                                <td class="py-2 text-center" data-cell="actions">
                                    <button type="button" @click="remove(index)" x-show="lines.length > 1" class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm" aria-label="Remove line">Remove</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button type="button" @click="add()" class="mt-4 inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                + Add line
            </button>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>{{ $slot }}</div>
        <x-card>
            <div class="p-4 sm:p-6 space-y-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $summaryTitle }}</h3>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="money(subtotal)"></span>
                </div>
                @if($documentDiscount)
                    <div class="flex items-center justify-between text-sm gap-2">
                        <div class="flex items-center gap-2">
                            <label for="discount_type" class="text-gray-600 dark:text-gray-400">Discount</label>
                            <select id="discount_type" name="discount_type" x-model="discountType" class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1">
                                <option value="">None</option>
                                <option value="percentage">%</option>
                                <option value="fixed">@currencySymbol</option>
                            </select>
                            <input type="number" name="discount_amount" aria-label="Discount amount" x-model.number="discountValue" x-show="discountType" min="0" step="0.01"
                                class="w-24 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1">
                        </div>
                        <span class="font-medium text-red-600 dark:text-red-400" x-text="'-' + money(discount)"></span>
                    </div>
                @endif
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">VAT</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100" x-text="money(tax)"></span>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                    <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400" x-text="money(total)"></span>
                </div>
            </div>
        </x-card>
    </div>
</div>

@once
    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        window.lookupJson = window.lookupJson || async function (url, params) {
            const response = await fetch(url + '?' + new URLSearchParams(params), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) return [];
            const body = await response.json();
            return body.data || [];
        };

        function salesLines(config) {
            let nextKey = 1;
            const blank = () => ({ key: nextKey++, item_id: '', itemSearch: '', description: '', quantity: 1, unit_price: 0, tax_rate: 0, discount: 0, open: false, highlighted: 0, results: [] });
            const start = (config.lines || []).map(l => Object.assign(blank(), l, { key: nextKey++ }));
            return {
                lines: start.length ? start : [blank()],
                discountType: config.discountType || '',
                discountValue: config.discountValue || 0,
                productsUrl: config.productsUrl,
                errors: @js(isset($errors) ? collect($errors->getMessages())->map(fn ($m) => $m[0])->all() : []),
                add() { this.lines.push(blank()); },
                remove(index) { this.lines.splice(index, 1); },
                search(index) {
                    const line = this.lines[index];
                    if (!line) return;
                    const seq = (line.seq || 0) + 1;
                    line.seq = seq;
                    window.lookupJson(this.productsUrl, { q: line.itemSearch || '', limit: 20 }).then(rows => {
                        if (line.seq !== seq) return;
                        line.results = rows.map(p => ({ id: String(p.id), name: p.name, price: Number(p.selling_price), desc: p.description || p.name, taxable: !!p.is_taxable, tax: Number(p.effective_tax_rate || 0) }));
                        line.highlighted = 0;
                    });
                },
                pick(index, product) {
                    if (!product) return;
                    const line = this.lines[index];
                    line.item_id = product.id;
                    line.itemSearch = product.name;
                    line.unit_price = product.price;
                    line.description = product.desc || product.name;
                    line.tax_rate = product.taxable ? product.tax : 0;
                    line.open = false;
                },
                net(line) { return Math.max(0, (Number(line.quantity) || 0) * (Number(line.unit_price) || 0) - (Number(line.discount) || 0)); },
                get subtotal() { return this.lines.reduce((sum, l) => sum + this.net(l), 0); },
                get discount() {
                    if (this.discountType === 'percentage') return this.subtotal * (Number(this.discountValue) || 0) / 100;
                    if (this.discountType === 'fixed') return Math.min(Number(this.discountValue) || 0, this.subtotal);
                    return 0;
                },
                // VAT is charged on what is left after the discount (A4).
                factor() { return this.subtotal > 0 ? Math.max(0, 1 - this.discount / this.subtotal) : 1; },
                lineTax(line) { return this.net(line) * this.factor() * (Number(line.tax_rate) || 0) / 100; },
                lineTotal(line) { return this.net(line) + this.lineTax(line); },
                get tax() { return this.lines.reduce((sum, l) => sum + this.lineTax(l), 0); },
                get total() { return this.subtotal - this.discount + this.tax; },
                money(value) { return typeof formatMoney === 'function' ? formatMoney(value) : Number(value).toFixed(2); },
            };
        }
    </script>
    @endpush
@endonce
