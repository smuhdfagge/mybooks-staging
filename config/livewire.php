<?php

// Only what differs from Livewire's own config (vendor/livewire/livewire/config/livewire.php).
return [
    // @livewireScripts (the admin layout) serves the CSP build of Alpine, as
    // resources/js/app.js imports it for the app (audit MB-29).
    'csp_safe' => true,
];
