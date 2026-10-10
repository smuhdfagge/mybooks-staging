{{--
    A small menu: the "⋯" row menu, "More" in page headers, filter pickers.
    Positioned against the window (fixed), so a table's sideways scroll
    never cuts it off. Closes on Escape, on a click outside, or on scroll.

    <x-table.dropdown>  (⋯ button)       <x-table.dropdown label="More">
        <x-table.menu-item href="…">View</x-table.menu-item> …
--}}
@props(['label' => null, 'srLabel' => 'Actions', 'align' => 'right', 'width' => 'w-52'])
<div class="inline-block text-left" x-data="{
        open: false, top: 0, left: 0,
        toggle() {
            if (this.open) { this.open = false; return; }
            const r = this.$refs.btn.getBoundingClientRect();
            this.open = true;
            this.$nextTick(() => {
                const m = this.$refs.menu.getBoundingClientRect();
                this.left = Math.max(8, Math.min(window.innerWidth - m.width - 8, {{ $align === 'right' ? 'r.right - m.width' : 'r.left' }}));
                this.top = (r.bottom + 4 + m.height > window.innerHeight && r.top - m.height - 4 > 0) ? r.top - m.height - 4 : r.bottom + 4;
                const first = this.$refs.menu.querySelector('a,button');
                if (first) first.focus();
            });
        }
    }" x-on:keydown.escape.window="open = false" x-on:scroll.window="open = false" x-on:resize.window="open = false">
    @if ($label)
        <button type="button" x-ref="btn" x-on:click="toggle()" :aria-expanded="open.toString()" aria-haspopup="menu"
            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
            {{ $label }}
            <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z" clip-rule="evenodd"/></svg>
        </button>
    @else
        <button type="button" x-ref="btn" x-on:click="toggle()" :aria-expanded="open.toString()" aria-haspopup="menu"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-transparent text-gray-600 hover:border-gray-300 hover:bg-white hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-800 dark:hover:text-white"
            :class="open && '!border-gray-300 !bg-white dark:!border-gray-600 dark:!bg-gray-800'">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm4.5 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM13 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/></svg>
            <span class="sr-only">{{ $srLabel }}</span>
        </button>
    @endif
    <div x-ref="menu" x-show="open" x-on:click.outside="open = false" x-on:click="if ($event.target.closest('a,button')) open = false" style="display: none"
        :style="`position: fixed; top: ${top}px; left: ${left}px`" role="menu"
        class="z-50 {{ $width }} rounded-lg border border-gray-200 bg-white p-1 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">
        {{ $slot }}
    </div>
</div>
