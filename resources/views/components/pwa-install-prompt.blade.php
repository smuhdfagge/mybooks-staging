<!-- PWA Install Prompt Component -->
<div x-data="pwaInstall()" 
     x-show="showPrompt" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-4"
     x-cloak
     class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-96 z-50">
    
    <!-- Install Banner -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Header -->
        <div class="bg-brand-900 px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <x-brand-mark class="w-10 h-10 flex-shrink-0" />
                    <div>
                        <h3 class="text-white font-semibold text-sm">Install MyBooks</h3>
                        <p class="text-white/80 text-xs">Add to your device</p>
                    </div>
                </div>
                <button @click="dismissPrompt()" class="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="p-4">
            <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">
                Install MyBooks on your device for quick access. Works offline and provides a native app experience!
            </p>

            <!-- Benefits -->
            <div class="space-y-2 mb-4">
                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Quick access from home screen</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Works offline</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>No app store required</span>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3">
                <button @click="dismissPrompt()" 
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-xl transition">
                    Not Now
                </button>
                <button @click="installApp()" 
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Install
                </button>
            </div>
        </div>
    </div>
</div>

<!-- iOS Install Instructions Modal -->
<div x-data="{ showIosModal: false }" @show-ios-install.window="showIosModal = true">
    <div x-show="showIosModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog" aria-modal="true" aria-label="Install MyBooks"
         @keydown.escape.window="showIosModal = false"
         x-cloak>
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity" @click="showIosModal = false"></div>
            
            <div x-show="showIosModal"
                 x-trap.inert.noscroll="showIosModal"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/50 mb-4">
                        <svg class="w-8 h-8 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Install on iOS</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Follow these steps to add MyBooks to your home screen:</p>
                    
                    <div class="text-left space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-300 rounded-full flex items-center justify-center text-sm font-medium">1</span>
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">Tap the <strong>Share</strong> button</p>
                                <div class="mt-1 flex items-center gap-1 text-gray-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                    </svg>
                                    <span class="text-xs">(at the bottom of Safari)</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-300 rounded-full flex items-center justify-center text-sm font-medium">2</span>
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">Scroll down and tap <strong>"Add to Home Screen"</strong></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-300 rounded-full flex items-center justify-center text-sm font-medium">3</span>
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">Tap <strong>"Add"</strong> to confirm</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button @click="showIosModal = false" 
                            class="w-full px-4 py-2.5 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ app('csp-nonce') }}">
function pwaInstall() {
    return {
        showPrompt: false,
        deferredPrompt: null,
        isIos: false,
        isStandalone: false,

        init() {
            // Check if already installed (standalone mode)
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches 
                || window.navigator.standalone 
                || document.referrer.includes('android-app://');

            // Check if iOS
            this.isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());

            // Don't show if already installed
            if (this.isStandalone) {
                return;
            }

            // Check if user has dismissed before (within last 7 days)
            const dismissedAt = localStorage.getItem('pwa-prompt-dismissed');
            if (dismissedAt) {
                const dismissedDate = new Date(parseInt(dismissedAt));
                const daysSinceDismissed = (Date.now() - dismissedDate) / (1000 * 60 * 60 * 24);
                if (daysSinceDismissed < 7) {
                    return;
                }
            }

            // Listen for beforeinstallprompt event (Chrome, Edge, etc.)
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                
                // Show prompt after a short delay
                setTimeout(() => {
                    this.showPrompt = true;
                }, 3000);
            });

            // For iOS, show prompt after delay (no beforeinstallprompt event)
            if (this.isIos && !this.isStandalone) {
                setTimeout(() => {
                    this.showPrompt = true;
                }, 5000);
            }

            // Listen for successful installation
            window.addEventListener('appinstalled', () => {
                this.showPrompt = false;
                this.deferredPrompt = null;
                console.log('PWA installed successfully');
            });
        },

        async installApp() {
            if (this.isIos) {
                // Show iOS instructions modal
                window.dispatchEvent(new CustomEvent('show-ios-install'));
                this.showPrompt = false;
                return;
            }

            if (!this.deferredPrompt) {
                console.log('No installation prompt available');
                return;
            }

            // Show the install prompt
            this.deferredPrompt.prompt();

            // Wait for user response
            const { outcome } = await this.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
            }

            this.deferredPrompt = null;
            this.showPrompt = false;
        },

        dismissPrompt() {
            this.showPrompt = false;
            localStorage.setItem('pwa-prompt-dismissed', Date.now().toString());
        }
    }
}
</script>
