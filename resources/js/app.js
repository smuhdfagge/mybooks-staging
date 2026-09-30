import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

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
