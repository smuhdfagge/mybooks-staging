// Helpers for Alpine expressions under the CSP build (audit MB-29). The CSP
// build evaluates x-* / @ / : attributes without eval, so an expression can
// only read what is in its component's scope: no globals (Math, Number,
// window.formatMoney...), no arrow functions, template literals, `?.`, `??`,
// `+=` or `;`. Anything more goes in a component registered with
// Alpine.data(), where it is ordinary JavaScript.
//
// In templates:
//   $money(amount)        formatMoney(): "₦1,234.50"
//   $money(amount, '$')   with another symbol
//   $math.round(x)        Math.round/min/max/abs/floor/ceil
//   $num(value)           Number(value)
//   $float(value)         parseFloat(value)
//   $do(a, b, ...)        run several expressions in order (instead of a; b)
//   $selectValue(v)       set this <select>'s value after Alpine's update
//   $indeterminate(bool)  a checkbox's half-ticked state (no `$el.x = ...`:
//                         the CSP build refuses DOM property assignments)
//
// And small components for what needs a callback:
//   x-data="autoHide(2000)"       x-show="show" hides itself after 2 s
//   x-data="reloadAfter(5000)"    reloads the page after 5 s
//   x-data="redirectAfter(url, 600)"
//   x-data="theme('dark')"        dark mode, kept in localStorage under that key
//   x-data="sidebar()"            the app's mobile drawer
//   x-data="photoPreview()"       @change="pick($event)" shows the chosen image
//   x-data="searchableDropdown({...})"  <x-searchable-select>, an ARIA combobox (U5)
//   x-data="toasts()"             <x-toast>
//   x-data="lockDateNotice({...})"  <x-lock-date-notice>
//   x-data="rowMenu('right')"     <x-table.dropdown>
//   x-data="fileDrop()"           a drop zone over x-ref="fileInput"

export function registerCspHelpers(Alpine) {
    Alpine.magic('money', () => (amount, symbol) => window.formatMoney(amount, symbol));
    Alpine.magic('math', () => ({
        round: Math.round,
        min: Math.min,
        max: Math.max,
        abs: Math.abs,
        floor: Math.floor,
        ceil: Math.ceil,
    }));
    Alpine.magic('num', () => (value) => Number(value));
    Alpine.magic('float', () => (value) => parseFloat(value));
    Alpine.magic('do', () => (...results) => results[results.length - 1]);
    // A checkbox's half-ticked state: assigning DOM properties in an
    // expression is refused by the CSP build.
    Alpine.magic('indeterminate', (el) => (value) => { el.indeterminate = !!value; });
    // A <select>'s value once Alpine has drawn its options (x-effect).
    Alpine.magic('selectValue', (el) => (value) => Alpine.nextTick(() => { el.value = String(value); }));

    Alpine.data('autoHide', (ms = 2000) => ({
        show: true,
        init() {
            setTimeout(() => { this.show = false; }, ms);
        },
    }));
    Alpine.data('reloadAfter', (ms = 5000) => ({
        init() {
            setTimeout(() => window.location.reload(), ms);
        },
    }));
    Alpine.data('redirectAfter', (url, ms = 600) => ({
        init() {
            setTimeout(() => { window.location.href = url; }, ms);
        },
    }));

    // partials/theme-init sets the class before paint; this keeps it in step.
    Alpine.data('theme', (key = 'dark', extra = {}) => ({
        ...extra,
        dark: document.documentElement.classList.contains('dark'),
        init() {
            this.$watch('dark', (value) => {
                try {
                    localStorage.setItem(key, value);
                } catch (e) {
                    // private mode: the choice lasts for this page only
                }
                document.documentElement.classList.toggle('dark', value);
            });
        },
    }));

    // Escape closes the drawer and puts focus back on the menu button (MB-57).
    Alpine.data('sidebar', () => ({
        sidebarOpen: false,
        closeSidebar() {
            if (!this.sidebarOpen) return;
            this.sidebarOpen = false;
            this.$nextTick(() => this.$refs.menuButton && this.$refs.menuButton.focus());
        },
    }));

    Alpine.data('photoPreview', () => ({
        preview: null,
        pick(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => { this.preview = e.target.result; };
            reader.readAsDataURL(file);
        },
    }));

    Alpine.data('searchableDropdown', ({ selected = '', options = [], placeholder = '', listId = 'options' } = {}) => ({
        open: false,
        search: '',
        active: -1,
        selected,
        options,
        placeholder,
        get filtered() {
            if (!this.search) return [{ v: '', l: this.placeholder }].concat(this.options);
            const q = this.search.toLowerCase();
            return this.options.filter((o) => o.l.toLowerCase().includes(q));
        },
        get selectedLabel() {
            const found = this.options.find((o) => o.v === this.selected);
            return found ? found.l : '';
        },
        optionId(i) {
            return listId + '-' + i;
        },
        show() {
            this.open = true;
            this.active = Math.max(0, this.filtered.findIndex((o) => o.v === this.selected));
            this.reveal();
        },
        close() {
            this.open = false;
            this.search = '';
            this.active = -1;
        },
        move(to) {
            if (!this.open) {
                this.show();
                return;
            }
            const n = this.filtered.length;
            if (!n) return;
            this.active = to === 'first' ? 0 : to === 'last' ? n - 1 : (this.active + to + n) % n;
            this.reveal();
        },
        // Home / End / Enter / Escape, only while the list is open.
        onKey(event, action) {
            if (!this.open) return;
            if (action === 'escape') {
                event.stopPropagation();
                this.close();
                return;
            }
            event.preventDefault();
            if (action === 'pick') {
                this.pickActive();
            } else {
                this.move(action);
            }
        },
        reveal() {
            this.$nextTick(() => {
                const el = document.getElementById(this.optionId(this.active));
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        },
        typed(text) {
            this.search = text;
            this.open = true;
            this.active = this.filtered.length ? 0 : -1;
        },
        choose(val) {
            this.selected = val;
            this.close();
            this.$refs.value.value = val;
            this.$refs.value.dispatchEvent(new Event('change', { bubbles: true }));
        },
        pickActive() {
            const o = this.filtered[this.active];
            if (o) this.choose(o.v);
        },
    }));

    // <x-toast>: @toast.window="add($event.detail)".
    Alpine.data('toasts', () => ({
        toasts: [],
        add(toast) {
            toast.id = Date.now() + Math.random();
            this.toasts.push(toast);
            setTimeout(() => this.remove(toast.id), toast.duration || 5000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    }));

    // <x-lock-date-notice>: watches the form's date field (session 11).
    Alpine.data('lockDateNotice', (config) => ({
        ...config,
        date: '',
        init() {
            const selector = "[name='" + this.field + "']";
            const input = (this.$el.closest('form') || document).querySelector(selector) || document.querySelector(selector);
            if (!input) return;
            const read = () => { this.date = input.value || ''; };
            read();
            input.addEventListener('input', read);
            input.addEventListener('change', read);
        },
        get state() {
            if (!this.date) return '';
            if (this.all && this.date <= this.all) return 'all';
            if (this.staff && this.date <= this.staff) return this.override ? 'warn' : 'staff';
            return '';
        },
    }));

    // <x-table.dropdown>: placed against the window, so a table's sideways
    // scroll never cuts it off; align 'right' or 'left' of its button.
    Alpine.data('rowMenu', (align = 'right') => ({
        open: false,
        top: 0,
        left: 0,
        toggle() {
            if (this.open) {
                this.open = false;
                return;
            }
            const r = this.$refs.btn.getBoundingClientRect();
            this.open = true;
            this.$nextTick(() => {
                const m = this.$refs.menu.getBoundingClientRect();
                this.left = Math.max(8, Math.min(window.innerWidth - m.width - 8, align === 'right' ? r.right - m.width : r.left));
                this.top = (r.bottom + 4 + m.height > window.innerHeight && r.top - m.height - 4 > 0) ? r.top - m.height - 4 : r.bottom + 4;
                const first = this.$refs.menu.querySelector('a,button');
                if (first) first.focus();
            });
        },
    }));

    // A file drop zone over a file input (x-ref="fileInput").
    Alpine.data('fileDrop', () => ({
        dragging: false,
        fileName: '',
        dropped(event) {
            this.dragging = false;
            const files = event.dataTransfer.files;
            this.fileName = files[0] ? files[0].name : '';
            this.$refs.fileInput.files = files;
        },
        chosen(event) {
            const file = event.target.files[0];
            this.fileName = file ? file.name : '';
        },
    }));
}
