// Script for the public pages (home page plan H5): only Alpine, for the
// phone menu and the monthly / yearly switch. The signed-in app keeps
// resources/js/app.js (Livewire, charts and the rest), which these pages do
// not need, so a phone downloads about 300 KB less.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
