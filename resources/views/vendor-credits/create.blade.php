<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New Supplier Credit</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">For goods you sent back, or a credit note from the supplier.</p>
            </div>
            <a href="{{ $bill ? route('bills.show', $bill) : route('vendor-credits.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400">Back</a>
        </div>
    </x-slot>

    @php
        $oldLines = old('items', $lines ?: [['item_id' => '', 'account_id' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0, 'tax_rate' => 0]]);
    @endphp

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            <form method="POST" action="{{ route('vendor-credits.store') }}" class="space-y-6"
                  x-data="vendorCreditForm(@js(array_values($oldLines)), @js($items->keyBy('id')))">
                @csrf
                <x-lock-date-notice field="credit_date" />
                @if($bill)
                    <input type="hidden" name="bill_id" value="{{ $bill->id }}">
                    <input type="hidden" name="vendor_id" value="{{ $bill->vendor_id }}">
                    <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 p-4 text-sm text-indigo-800 dark:text-indigo-200">
                        Returning goods from bill <a href="{{ route('bills.show', $bill) }}" class="font-semibold underline">{{ $bill->bill_number }}</a> ({{ $bill->vendor->name }}).
                        Put the quantity you are sending back on each line, and leave the rest at 0.
                    </div>
                @endif

                <x-card class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="form-label" for="number">Credit number</label>
                            <input id="number" type="text" value="{{ $number }}" disabled class="form-control bg-gray-100 dark:bg-gray-600">
                        </div>
                        @unless($bill)
                            <div>
                                <x-searchable-select name="vendor_id" id="vendor_id" label="Supplier *" :options="$vendors->all()" :value="old('vendor_id', $vendorId)" :has-error="$errors->has('vendor_id')" placeholder="Choose a supplier" />
                                @error('vendor_id')<p id="vendor_id-error" class="form-error">{{ $message }}</p>@enderror
                            </div>
                        @endunless
                        <div>
                            <x-field name="credit_date" label="Credit date" type="date" :value="old('credit_date', now()->toDateString())" required />
                        </div>
                        <div>
                            <x-field name="vendor_reference" label="Supplier's credit note number" :value="old('vendor_reference')" />
                        </div>
                        <div>
                            <x-field name="reason" label="Reason" type="select">
                                <option value="">—</option>
                                @foreach($reasons as $key => $label)
                                    <option value="{{ $key }}" @selected(old('reason', $bill ? 'goods_returned' : null) === $key)>{{ $label }}</option>
                                @endforeach
                            </x-field>
                        </div>
                    </div>
                </x-card>

                <x-card class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Lines</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Choose an item for goods going back (they leave stock at what they cost you). For a price correction with no goods, leave the item empty and pick the expense account to reduce.</p>
                    @error('items')<p class="form-error mb-3">{{ $message }}</p>@enderror

                    <table class="min-w-full line-items">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-sm text-gray-700 dark:text-gray-300">
                                <th class="pb-2 pr-2">Item / account</th>
                                <th class="pb-2 pr-2">Description</th>
                                <th class="pb-2 pr-2 w-24">Qty</th>
                                <th class="pb-2 pr-2 w-32">Price</th>
                                <th class="pb-2 pr-2 w-20">VAT %</th>
                                <th class="pb-2 pr-2 w-32 text-right">Total</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, i) in lines" :key="i">
                                <tr class="border-b border-gray-200 dark:border-gray-700 align-top">
                                    <td class="py-2 pr-2" data-cell="main" data-label="Item / account">
                                        <select :name="`items[${i}][item_id]`" x-model="line.item_id" @change="pickItem(line)" class="form-control text-sm" aria-label="Item">
                                            <option value="">No item (price correction)</option>
                                            @foreach($items as $item)
                                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                                            @endforeach
                                        </select>
                                        <select x-show="!line.item_id" :name="`items[${i}][account_id]`" x-model="line.account_id" class="form-control text-sm mt-1" aria-label="Account">
                                            <option value="">Miscellaneous expense</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->account_code }} {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <p x-show="line.billed" class="form-help" x-text="`On the bill: ${line.billed}`"></p>
                                    </td>
                                    <td class="py-2 pr-2" data-cell="main" data-label="Description">
                                        <input type="text" :name="`items[${i}][description]`" x-model="line.description" class="form-control text-sm" aria-label="Description">
                                    </td>
                                    <td class="py-2 pr-2" data-label="Qty">
                                        <input type="number" step="any" min="0" :name="`items[${i}][quantity]`" x-model.number="line.quantity" class="form-control text-sm" aria-label="Quantity">
                                    </td>
                                    <td class="py-2 pr-2" data-label="Price">
                                        <input type="number" step="0.01" min="0" :name="`items[${i}][unit_price]`" x-model.number="line.unit_price" class="form-control text-sm" aria-label="Price">
                                    </td>
                                    <td class="py-2 pr-2" data-label="VAT %">
                                        <input type="number" step="0.01" min="0" max="100" :name="`items[${i}][tax_rate]`" x-model.number="line.tax_rate" class="form-control text-sm" aria-label="VAT rate">
                                    </td>
                                    <td class="py-2 pr-2 text-right text-sm text-gray-900 dark:text-gray-100" data-label="Total" x-text="money(lineTotal(line))"></td>
                                    <td class="py-2" data-cell="actions">
                                        <button type="button" @click="lines.splice(i, 1)" x-show="lines.length > 1" class="text-red-600 text-sm" aria-label="Remove line">Remove</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-3 mt-4">
                        <button type="button" @click="lines.push({item_id: '', account_id: '', description: '', quantity: 1, unit_price: 0, tax_rate: 0})" class="text-sm text-indigo-600 dark:text-indigo-400 text-left">+ Add a line</button>
                        <dl class="text-sm text-gray-700 dark:text-gray-300 sm:text-right space-y-1">
                            <div>Before VAT: <span class="font-medium" x-text="money(subtotal())"></span></div>
                            <div>VAT: <span class="font-medium" x-text="money(vat())"></span></div>
                            <div class="text-base">Credit total: <span class="font-semibold" x-text="money(subtotal() + vat())"></span></div>
                        </dl>
                    </div>
                </x-card>

                <x-card class="p-6">
                    <x-field name="notes" label="Notes" type="textarea" rows="2" :value="old('notes')" />
                </x-card>

                <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                    <button type="submit" name="status" value="draft" class="inline-flex justify-center items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:text-gray-200">Save as draft</button>
                    <button type="submit" name="status" value="open" class="btn-primary">Save and post</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function vendorCreditForm(lines, items) {
            return {
                lines: lines.map(l => Object.assign({item_id: '', account_id: '', billed: null}, l, {item_id: l.item_id ? String(l.item_id) : '', account_id: l.account_id ? String(l.account_id) : ''})),
                pickItem(line) {
                    const item = items[line.item_id];
                    if (!item) return;
                    if (!line.description) line.description = item.name;
                    if (!line.unit_price) line.unit_price = Number(item.cost_price || 0);
                },
                round(n) { return Math.round((Number(n) + Number.EPSILON) * 100) / 100; },
                lineNet(l) { return this.round((Number(l.quantity) || 0) * (Number(l.unit_price) || 0)); },
                lineVat(l) { return this.round(this.lineNet(l) * (Number(l.tax_rate) || 0) / 100); },
                lineTotal(l) { return this.lineNet(l) + this.lineVat(l); },
                subtotal() { return this.lines.reduce((s, l) => s + this.lineNet(l), 0); },
                vat() { return this.lines.reduce((s, l) => s + this.lineVat(l), 0); },
                money(n) { return (window.formatMoney ? window.formatMoney(n) : Number(n).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})); },
            };
        }
    </script>
    @endpush
</x-app-layout>
