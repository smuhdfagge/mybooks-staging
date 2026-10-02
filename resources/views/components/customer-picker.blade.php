{{--
    Customer search box for sales document forms (quotations, credit notes).
    Customers are searched as you type through the lookup route (P9), like
    the invoice form, so the page doesn't load every customer.

    <x-customer-picker :options="$customerOptions" :value="old('customer_id', $quotation->customer_id ?? '')" />
--}}
@props(['options' => [], 'value' => '', 'name' => 'customer_id', 'label' => 'Customer', 'required' => true, 'locked' => false])

@php($error = isset($errors) ? $errors->first($name) : null)

<div x-data="customerPicker({ items: @js($options), url: @js(route('lookup.customers')), selectedId: @js((string) $value) })" class="relative">
    <label for="{{ $name }}_search" class="form-label">{{ $label }}@if($required) <span class="text-red-500">*</span>@endif</label>
    <input type="hidden" name="{{ $name }}" :value="selectedId">
    @if($locked)
        <input type="text" id="{{ $name }}_search" :value="search" disabled class="form-control bg-gray-100 dark:bg-gray-600">
    @else
        <input type="text" id="{{ $name }}_search" role="combobox" autocomplete="off"
            aria-autocomplete="list" aria-controls="{{ $name }}-listbox" :aria-expanded="open.toString()"
            @if($error) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
            x-model="search" @focus="open = true" @click="open = true" @input="open = true"
            @keydown.escape="open = false"
            @keydown.arrow-down.prevent="highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1)"
            @keydown.arrow-up.prevent="highlightedIndex = Math.max(highlightedIndex - 1, 0)"
            @keydown.enter.prevent="items[highlightedIndex] && selectItem(items[highlightedIndex])"
            placeholder="Search customers..."
            class="form-control {{ $error ? 'border-red-500' : '' }}">
        <ul id="{{ $name }}-listbox" role="listbox" x-show="open" x-cloak @click.outside="open = false"
            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 ring-1 ring-black ring-opacity-5 overflow-auto text-sm">
            <template x-for="(item, index) in items" :key="item.id">
                <li role="option" :aria-selected="(selectedId == item.id).toString()"
                    @mousedown.prevent @click="selectItem(item)" @mouseenter="highlightedIndex = index"
                    :class="highlightedIndex === index ? 'bg-indigo-600 text-white' : 'text-gray-900 dark:text-gray-100'"
                    class="cursor-pointer select-none py-2 px-3" x-text="item.name"></li>
            </template>
            <li x-show="items.length === 0" role="presentation" class="py-2 px-3 text-gray-500 dark:text-gray-400">No customers found</li>
        </ul>
    @endif
    @if($error)
        <p id="{{ $name }}-error" class="form-error">{{ $error }}</p>
    @endif
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

        function customerPicker(config) {
            return {
                items: config.items || [],
                url: config.url,
                selectedId: config.selectedId || '',
                search: '',
                open: false,
                highlightedIndex: 0,
                seq: 0,
                timer: null,
                init() {
                    const selected = this.items.find(i => i.id == this.selectedId);
                    if (selected) this.search = selected.name;
                    this.$watch('search', () => this.fetch());
                    this.$watch('open', (isOpen) => { if (isOpen) this.fetch(); });
                },
                fetch() {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(async () => {
                        const seq = ++this.seq;
                        const rows = await window.lookupJson(this.url, { q: this.search, limit: 20 });
                        if (seq !== this.seq) return;
                        this.items = rows.map(r => ({ id: String(r.id), name: r.name + (r.company_name ? ` (${r.company_name})` : '') }));
                        this.highlightedIndex = 0;
                    }, 250);
                },
                selectItem(item) {
                    this.selectedId = item.id;
                    this.search = item.name;
                    this.open = false;
                    this.$dispatch('customer-picked', { id: item.id });
                },
            };
        }
    </script>
    @endpush
@endonce
