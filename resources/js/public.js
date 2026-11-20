// Script for the public pages (home page plan H5): only Alpine, for the
// phone menu and the monthly / yearly switch. The signed-in app keeps
// resources/js/app.js (Livewire, charts and the rest), which these pages do
// not need, so a phone downloads about 300 KB less. The CSP build, as in the
// app (audit MB-29).
import Alpine from '@alpinejs/csp';
import { registerCspHelpers } from './alpine-csp';

window.Alpine = Alpine;
registerCspHelpers(Alpine);
Alpine.start();
