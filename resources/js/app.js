import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Livewire's ESM already bundles Alpine plugins (collapse, persist, morph, etc.)
// Only start manually when @livewireScriptConfig was rendered (which prevents the
// built-in DOMContentLoaded auto-start). This avoids a double-start race condition
// that causes "Cannot redefine property: $persist".
if (window.livewireScriptConfig !== undefined) {
    Livewire.start();
}
