{{-- Shared public site footer. Pass $active = 'about'|'contact'|'support'|'privacy-policy'|'terms-of-service' to highlight the current page. --}}
@php($active = $active ?? null)
@php($linkClass = fn (string $page) => $active === $page ? 'text-white font-medium' : 'hover:text-white transition')

<footer class="bg-gray-900 text-gray-300 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
            <div class="col-span-2 md:col-span-1">
                <h3 class="text-white font-bold text-xl mb-4">MyBooks</h3>
                <p class="text-gray-400 text-sm">Complete accounting and bookkeeping solution for modern businesses.</p>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-4">Product</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('home') }}#features" class="hover:text-white transition">Features</a></li>
                    <li><a href="{{ route('home') }}#modules" class="hover:text-white transition">Modules</a></li>
                    <li><a href="{{ route('home') }}#how-it-works" class="hover:text-white transition">How It Works</a></li>
                    <li><a href="{{ route('home') }}#pricing" class="hover:text-white transition">Pricing</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-4">Company</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('about') }}" class="{{ $linkClass('about') }}">About Us</a></li>
                    <li><a href="{{ route('contact') }}" class="{{ $linkClass('contact') }}">Contact</a></li>
                    <li><a href="{{ route('support') }}" class="{{ $linkClass('support') }}">Support</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-4">Legal</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('privacy-policy') }}" class="{{ $linkClass('privacy-policy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('terms-of-service') }}" class="{{ $linkClass('terms-of-service') }}">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 text-center text-sm text-gray-400">
            <p>&copy; {{ date('Y') }} MyBooks. All rights reserved.</p>
        </div>
    </div>
</footer>
