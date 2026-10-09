<!-- Top Header -->
<header class="sticky top-0 z-30 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
    <div class="flex h-16 items-center justify-between px-3 sm:px-4 lg:px-6 gap-3">
        <!-- Mobile menu button -->
        <button @click="sidebarOpen = true" 
                class="lg:hidden -ml-1 p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-500">
            <span class="sr-only">Open sidebar</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Search - Hidden on very small screens -->
        <div class="hidden sm:flex flex-1 max-w-md">
            <livewire:global-search />
        </div>

        <!-- Spacer for mobile -->
        <div class="flex-1 sm:hidden"></div>

        <!-- Right side items -->
        <div class="flex items-center gap-1 sm:gap-3">
            <!-- Mobile search button -->
            <button class="sm:hidden p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-md dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700" aria-label="Search">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>

            <!-- Notifications -->
            <button class="relative p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-md dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700">
                <span class="sr-only">View notifications</span>
                <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span class="absolute top-1 right-1 sm:top-1.5 sm:right-1.5 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-gray-800"></span>
            </button>

            <!-- Dark mode toggle -->
            <button @click="dark = !dark"
                    class="p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-md dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700">
                <span class="sr-only">Toggle dark mode</span>
                <svg x-show="!dark" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
                <svg x-show="dark" x-cloak class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </button>

            <!-- User dropdown -->
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" 
                        class="flex items-center gap-2 p-1 sm:p-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <div class="h-8 w-8 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0">
                        <span class="text-sm font-medium text-white">{{ auth()->user() ? strtoupper(substr(auth()->user()->name, 0, 1)) : 'G' }}</span>
                    </div>
                    <span class="hidden md:block text-gray-700 dark:text-gray-300 max-w-[120px] truncate">{{ auth()->user()->name ?? 'Guest' }}</span>
                    <svg class="hidden sm:block h-4 w-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" 
                     @click.away="open = false"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-2 w-48 origin-top-right rounded-lg bg-white dark:bg-gray-700 py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50"
                     x-cloak>
                    <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-600 md:hidden">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ auth()->user()->name ?? 'Guest' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ auth()->user()->email ?? '' }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">Your Profile</a>
                    <a href="{{ route('settings.company') }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">Settings</a>
                    <!-- Install App Button -->
                    <button type="button" 
                            data-call="triggerPwaInstall" 
                            id="install-app-menu-btn"
                            class="hidden w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Install App
                        </span>
                    </button>
                    <hr class="my-1 border-gray-200 dark:border-gray-600">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Install App Script for Header Menu -->
<script nonce="{{ app('csp-nonce') }}">
    // Global install prompt handler
    let deferredInstallPrompt = null;
    const isIosDevice = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    const isStandaloneMode = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstallPrompt = e;
        // Show the install button in menu
        const installBtn = document.getElementById('install-app-menu-btn');
        if (installBtn && !isStandaloneMode) {
            installBtn.classList.remove('hidden');
        }
    });

    // Show install button for iOS
    if (isIosDevice && !isStandaloneMode) {
        document.addEventListener('DOMContentLoaded', () => {
            const installBtn = document.getElementById('install-app-menu-btn');
            if (installBtn) {
                installBtn.classList.remove('hidden');
            }
        });
    }

    function triggerPwaInstall() {
        if (isIosDevice) {
            // Show iOS instructions modal
            window.dispatchEvent(new CustomEvent('show-ios-install'));
            return;
        }

        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                }
                deferredInstallPrompt = null;
            });
        }
    }

    // Hide install button when app is installed
    window.addEventListener('appinstalled', () => {
        const installBtn = document.getElementById('install-app-menu-btn');
        if (installBtn) {
            installBtn.classList.add('hidden');
        }
    });
</script>
