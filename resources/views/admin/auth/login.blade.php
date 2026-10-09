<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ dark: document.documentElement.classList.contains('dark') }" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.theme-init', ['key' => 'adminDark'])
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin Login - MyBooks</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <script nonce="{{ app('csp-nonce') }}">
        // Refresh page if it's been idle for more than 2 hours to get fresh CSRF token
        let lastActivity = Date.now();
        const maxIdleTime = 2 * 60 * 60 * 1000; // 2 hours
        
        window.addEventListener('load', function() {
            if (Date.now() - lastActivity > maxIdleTime) {
                window.location.reload();
            }
        });
    </script>
</head>
<body class="font-sans antialiased bg-brand-950 min-h-screen flex items-center justify-center">
    
    <!-- Dark Mode Toggle -->
    <button @click="dark = !dark; localStorage.setItem('adminDark', dark)" 
            class="fixed top-4 right-4 p-2 rounded-lg bg-white/10 hover:bg-white/20 transition">
        <svg x-show="!dark" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
        <svg x-show="dark" class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
    </button>

    <div class="w-full max-w-md px-6">
        <!-- Logo -->
        <div class="text-center mb-8">
            <x-brand-mark class="inline-block w-16 h-16 mb-4" />
            <h1 class="text-3xl font-bold text-white">MyBooks Admin</h1>
            <p class="text-brand-200 dark:text-gray-400 mt-2">Tenant Management Portal</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">Sign in to your account</h2>

            @if(session('error'))
                <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/50 border border-red-200 dark:border-red-700 rounded-lg">
                    <p class="text-sm text-red-600 dark:text-red-400">{{ session('error') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email address</label>
                        <input type="email" 
                               name="email" 
                               id="email" 
                               value="{{ old('email') }}"
                               required 
                               autofocus
                               class="mt-1 w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                               placeholder="admin@example.com" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')
                            <p id="email-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               required
                               class="mt-1 w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                               placeholder="••••••••" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')
                            <p id="password-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full py-3 px-4 bg-brand-600 hover:bg-brand-500 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition duration-200">
                        Sign in
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-brand-200 dark:text-gray-400 text-sm mt-6">
            &copy; {{ date('Y') }} MyBooks. Admin Portal.
        </p>
    </div>
</body>
</html>
