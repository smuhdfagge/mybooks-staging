{{-- Shared public site navigation. Pass $active = 'about'|'contact'|'support'|'privacy-policy'|'terms-of-service' to highlight the current page. --}}
@php($active = $active ?? null)
@php($anchorClass = 'text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition')
@php($linkClass = fn (string $page) => $active === $page
    ? 'text-indigo-600 dark:text-indigo-400 font-medium'
    : $anchorClass)

<nav class="fixed w-full bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <a href="{{ route('home') }}" class="text-2xl font-bold gradient-text">MyBooks</a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="{{ route('home') }}#features" class="{{ $anchorClass }}">Features</a>
                <a href="{{ route('home') }}#how-it-works" class="{{ $anchorClass }}">How It Works</a>
                <a href="{{ route('home') }}#modules" class="{{ $anchorClass }}">Modules</a>
                <a href="{{ route('home') }}#pricing" class="{{ $anchorClass }}">Pricing</a>
                <a href="{{ route('about') }}" class="{{ $linkClass('about') }}">About Us</a>
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Get Started</a>
                        @endif
                    @endauth
                @endif
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none"
                    aria-label="Toggle navigation menu"
                    :aria-expanded="mobileMenuOpen"
                    aria-controls="mobile-menu"
                >
                    <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" x-show="mobileMenuOpen" x-cloak x-transition class="md:hidden pb-4">
            <div class="flex flex-col space-y-4">
                <a href="{{ route('home') }}#features" @click="mobileMenuOpen = false" class="{{ $anchorClass }}">Features</a>
                <a href="{{ route('home') }}#how-it-works" @click="mobileMenuOpen = false" class="{{ $anchorClass }}">How It Works</a>
                <a href="{{ route('home') }}#modules" @click="mobileMenuOpen = false" class="{{ $anchorClass }}">Modules</a>
                <a href="{{ route('home') }}#pricing" @click="mobileMenuOpen = false" class="{{ $anchorClass }}">Pricing</a>
                <a href="{{ route('about') }}" @click="mobileMenuOpen = false" class="{{ $linkClass('about') }}">About Us</a>
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-center">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-center">Get Started</a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </div>
</nav>
