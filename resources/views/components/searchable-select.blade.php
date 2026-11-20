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

{{-- The behaviour is the searchableDropdown component (resources/js/alpine-csp.js). --}}
<div x-data="searchableDropdown({ selected: @js((string) $value), options: @js($normalized), placeholder: @js($placeholder), listId: @js($listId) })" @click.outside="close()" class="relative">
    @if($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    @endif
    <input type="hidden" name="{{ $name }}" :value="selected" x-ref="value" value="{{ $value }}">
    <div class="relative">
        <input type="text" id="{{ $id }}" role="combobox" autocomplete="off"
            aria-autocomplete="list" aria-controls="{{ $listId }}"
            :aria-expanded="open.toString()"
            :aria-activedescendant="open && active >= 0 ? optionId(active) : null"
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            :value="open ? search : selectedLabel"
            :placeholder="open ? @js($searchPlaceholder) : (selectedLabel || placeholder)"
            @input="typed($event.target.value)"
            @click="open ? close() : show()"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            @keydown.home="onKey($event, 'first')"
            @keydown.end="onKey($event, 'last')"
            @keydown.enter="onKey($event, 'pick')"
            @keydown.escape="onKey($event, 'escape')"
            @keydown.tab="close()"
            class="w-full rounded-md border shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm pl-3 pr-8 py-2 bg-white dark:bg-gray-700 dark:text-gray-300 placeholder-gray-500 dark:placeholder-gray-400 {{ $hasError ? 'border-red-500' : 'border-gray-300 dark:border-gray-600' }}">
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
                :class="{ 'bg-brand-100 dark:bg-gray-600': active === i, 'font-medium': selected === opt.v }"
                x-text="opt.l"></li>
        </template>
        <li x-show="filtered.length === 0" role="presentation" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No results found</li>
    </ul>
</div>
