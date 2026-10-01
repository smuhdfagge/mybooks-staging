import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Show money the same way as @money() in Blade (U8): the business's currency
// symbol (from <meta name="currency-symbol">), thousands separators and 2
// decimals, e.g. formatMoney(1234.5) -> "₦1,234.50". Pass a symbol to override.
window.formatMoney = (amount, symbol) => {
    const value = Number(amount) || 0;
    const sign = value < 0 ? '-' : '';
    if (symbol === undefined) {
        symbol = document.querySelector('meta[name="currency-symbol"]')?.content ?? '';
    }
    return sign + symbol + Math.abs(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
// (Defined before Livewire starts Alpine, so x-text="formatMoney(...)" works on first paint.)

// Livewire's ESM already bundles Alpine plugins (collapse, persist, morph, etc.)
// Only start manually when @livewireScriptConfig was rendered (which prevents the
// built-in DOMContentLoaded auto-start). This avoids a double-start race condition
// that causes "Cannot redefine property: $persist".
if (window.livewireScriptConfig !== undefined) {
    Livewire.start();
}

// Chart.js is bundled rather than taken from a CDN (U14), and loaded only on
// pages that draw charts: window.loadChart().then(Chart => new Chart(...)).
window.loadChart = () => import('chart.js/auto').then(({ default: Chart }) => {
    window.Chart = Chart;
    return Chart;
});

