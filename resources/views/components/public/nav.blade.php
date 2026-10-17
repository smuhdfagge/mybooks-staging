{{--
    Public top bar (home page plan H2, from the sample design). The section
    links point at the home page, so they work from every public page.
--}}
@php
    $home = route('home');
    $links = [
        ['What it does', $home.'#tour'],
        ['Nigerian tax', $home.'#rules'],
        ['Pricing', $home.'#pricing'],
        ['For accountants', $home.'#accountants'],
        ['Questions', $home.'#faq'],
    ];
    $link = 'rounded text-[0.95rem] font-medium text-gray-600 hover:text-gray-900 focus-ring dark:text-gray-300 dark:hover:text-white';
@endphp

<header class="sticky top-0 z-50 border-b border-gray-200 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-7 px-4 sm:px-6 lg:h-[68px]">
        <a href="{{ $home }}" class="flex shrink-0 items-center gap-2.5 rounded text-xl font-semibold text-gray-900 focus-ring dark:text-white">
            <x-brand-mark class="h-[30px] w-[30px]" /><span>MyBooks</span>
        </a>

        <nav class="hidden items-center gap-6 lg:flex" aria-label="Main">
            @foreach ($links as [$label, $href])
                <a href="{{ $href }}" class="{{ $link }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2.5">
            @auth
                <a href="{{ url('/dashboard') }}" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg bg-brand-600 px-4 text-[0.95rem] font-semibold text-white hover:bg-brand-700 focus-ring">Open MyBooks</a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="hidden rounded px-2.5 py-2 font-medium text-gray-900 focus-ring hover:text-brand-600 dark:text-white dark:hover:text-brand-300 sm:inline-block">Sign in</a>
                @endif
                <a href="{{ $home }}#pricing" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg bg-brand-600 px-4 text-[0.95rem] font-semibold text-white hover:bg-brand-700 focus-ring">Choose a plan</a>
            @endauth

            <button type="button" class="inline-grid h-10 w-10 place-items-center rounded-lg text-gray-700 hover:bg-gray-100 focus-ring dark:text-gray-200 dark:hover:bg-gray-800 lg:hidden"
                @click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen.toString()" aria-controls="public-menu"
                :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'" aria-label="Open menu">
                <svg x-show="!mobileMenuOpen" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileMenuOpen" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div id="public-menu" x-show="mobileMenuOpen" x-cloak x-transition.opacity class="border-t border-gray-200 bg-white px-4 pb-5 pt-2 dark:border-gray-800 dark:bg-gray-900 lg:hidden">
        <nav class="flex flex-col" aria-label="Main (menu)">
            @foreach ($links as [$label, $href])
                <a href="{{ $href }}" @click="mobileMenuOpen = false" class="rounded py-3 text-base font-medium text-gray-800 focus-ring dark:text-gray-100">{{ $label }}</a>
            @endforeach
            @guest
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="rounded py-3 text-base font-medium text-gray-800 focus-ring dark:text-gray-100">Sign in</a>
                @endif
            @endguest
        </nav>
    </div>
</header>
