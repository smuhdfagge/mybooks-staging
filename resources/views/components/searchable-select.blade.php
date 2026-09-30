{{--
    Searchable dropdown (U5), built as an ARIA combobox: a text box with
    role="combobox" that filters a role="listbox" list. Keyboard: type to
    filter, Up/Down to move, Enter to pick, Escape to close, Home/End.
    The highlighted option is announced through aria-activedescendant.

    <x-searchable-select name="country" label="Country" :options="$list" :value="old('country')"
                         :has-error="$errors->has('country')" />

    options: a plain list (value = label) or [value => label]. The chosen
    value is posted in a hidden input named "name". Give "id" to point an
    outside <label for> at it; it defaults to the field name.
--}}
@props([
    'name',
    'label' => '',
    'options' => [],
    'value' => '',
    'placeholder' => 'Select...',
    'searchPlaceholder' => 'Search...',
    'hasError' => false,
    'id' => null,
])

@php
    $normalized = [];
    $isList = array_is_list($options);
    foreach ($options as $key => $opt) {
        $normalized[] = $isList
            ? ['v' => (string) $opt, 'l' => (string) $opt]
            : ['v' => (string) $key, 'l' => (string) $opt];
    }
    $id = $id ?? trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $listId = $id.'-listbox';
@endphp

<div x-data="{
    open: false,
    search: '',
    active: -1,
    selected: @js((string) $value),
    options: @js($normalized),
    placeholder: @js($placeholder),
    get filtered() {
        if (!this.search) return [{ v: '', l: this.placeholder }].concat(this.options);
        const q = this.search.toLowerCase();
        return this.options.filter(o => o.l.toLowerCase().includes(q));
    },
    get selectedLabel() {
        const found = this.options.find(o => o.v === this.selected);
        return found ? found.l : '';
    },
    optionId(i) { return @js($listId) + '-' + i; },
    show() {
        this.open = true;
        this.active = Math.max(0, this.filtered.findIndex(o => o.v === this.selected));
        this.reveal();
    },
    close() { this.open = false; this.search = ''; this.active = -1; },
    move(to) {
        if (!this.open) { this.show(); return; }
        const n = this.filtered.length;
        if (!n) return;
        this.active = to === 'first' ? 0 : to === 'last' ? n - 1 : (this.active + to + n) % n;
        this.reveal();
    },
    reveal() {
        this.$nextTick(() => { const el = document.getElementById(this.optionId(this.active)); if (el) el.scrollIntoView({ block: 'nearest' }); });
    },
    typed(text) { this.search = text; this.open = true; this.active = this.filtered.length ? 0 : -1; },
    choose(val) {
        this.selected = val;
        this.close();
        this.$refs.value.value = val;
        this.$refs.value.dispatchEvent(new Event('change', { bubbles: true }));
    },
    pickActive() { const o = this.filtered[this.active]; if (o) this.choose(o.v); }
}" @click.outside="close()" class="relative">
    @if($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    @endif
    <input type="hidden" name="{{ $name }}" :value="selected" x-ref="value" value="{{ $value }}">
    <div class="relative">
        <input type="text" id="{{ $id }}" role="combobox" autocomplete="off"
            aria-autocomplete="list" aria-controls="{{ $listId }}"
            :aria-expanded="open.toString()"
            :aria-activedescendant="open && active >= 0 ? optionId(active) : null"
            @if($hasError) aria-invalid="true" @endif
            :value="open ? search : selectedLabel"
            :placeholder="open ? @js($searchPlaceholder) : (selectedLabel || placeholder)"
            @input="typed($event.target.value)"
            @click="open ? close() : show()"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            @keydown.home="open && ($event.preventDefault(), move('first'))"
            @keydown.end="open && ($event.preventDefault(), move('last'))"
            @keydown.enter="open && ($event.preventDefault(), pickActive())"
            @keydown.escape="open && ($event.stopPropagation(), close())"
            @keydown.tab="close()"
            class="w-full rounded-md border shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pl-3 pr-8 py-2 bg-white dark:bg-gray-700 dark:text-gray-300 placeholder-gray-500 dark:placeholder-gray-400 {{ $hasError ? 'border-red-500' : 'border-gray-300 dark:border-gray-600' }}">
        <svg aria-hidden="true" class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
    <ul id="{{ $listId }}" role="listbox" @if($label) aria-label="{{ $label }}" @endif
        x-show="open" x-cloak
        class="absolute z-50 mt-1 w-full max-h-48 overflow-y-auto py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg">
        <template x-for="(opt, i) in filtered" :key="opt.v + '|' + i">
            <li :id="optionId(i)" role="option" :aria-selected="(selected === opt.v).toString()"
                @mousedown.prevent @click="choose(opt.v)" @mousemove="active = i"
                class="px-3 py-2 text-sm cursor-pointer dark:text-gray-200"
                :class="{ 'bg-indigo-100 dark:bg-gray-600': active === i, 'font-medium': selected === opt.v }"
                x-text="opt.l"></li>
        </template>
        <li x-show="filtered.length === 0" role="presentation" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No results found</li>
    </ul>
</div>
