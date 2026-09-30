@props([
    'name',
    'label' => '',
    'options' => [],
    'value' => '',
    'placeholder' => 'Select...',
    'searchPlaceholder' => 'Search...',
    'hasError' => false,
])

@php
    $normalized = [];
    $isList = array_is_list($options);
    foreach ($options as $key => $opt) {
        $normalized[] = $isList
            ? ['v' => (string)$opt, 'l' => (string)$opt]
            : ['v' => (string)$key, 'l' => (string)$opt];
    }
@endphp

<div x-data="{
    open: false,
    search: '',
    selected: @js((string)$value),
    options: @js($normalized),
    get filtered() {
        if (!this.search) return this.options;
        return this.options.filter(o => o.l.toLowerCase().includes(this.search.toLowerCase()));
    },
    get selectedLabel() {
        const found = this.options.find(o => o.v === this.selected);
        return found ? found.l : '';
    },
    choose(val) {
        this.selected = val;
        this.open = false;
        this.search = '';
    }
}" @click.outside="open = false; search = ''" class="relative">
    @if($label)
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" name="{{ $name }}" :value="selected">
    <button type="button" @click="open = !open"
        class="w-full rounded-md border shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-left px-3 py-2 bg-white dark:bg-gray-700 dark:text-gray-300 flex items-center justify-between {{ $hasError ? 'border-red-500' : 'border-gray-300 dark:border-gray-600' }}">
        <span x-text="selectedLabel || '{{ $placeholder }}'" class="truncate" :class="{ 'text-gray-500 dark:text-gray-400': !selected }"></span>
        <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" x-cloak x-transition.opacity class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg">
        <div class="p-2">
            <input type="text" x-model="search" placeholder="{{ $searchPlaceholder }}"
                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white text-sm px-2 py-1.5 focus:border-indigo-500 focus:ring-indigo-500"
                @click.stop x-ref="searchInput">
        </div>
        <ul class="max-h-48 overflow-y-auto py-1">
            <li @click="choose('')" class="px-3 py-2 text-sm cursor-pointer hover:bg-indigo-50 dark:hover:bg-gray-600 dark:text-gray-200"
                :class="{ 'bg-indigo-50 dark:bg-gray-600 font-medium': !selected }">{{ $placeholder }}</li>
            <template x-for="opt in filtered" :key="opt.v">
                <li @click="choose(opt.v)" class="px-3 py-2 text-sm cursor-pointer hover:bg-indigo-50 dark:hover:bg-gray-600 dark:text-gray-200"
                    :class="{ 'bg-indigo-50 dark:bg-gray-600 font-medium': selected === opt.v }"
                    x-text="opt.l"></li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No results found</li>
        </ul>
    </div>
</div>
