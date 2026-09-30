<x-guest-layout>
    <!-- Logo -->
    <div class="flex justify-center mb-6">
        <svg class="h-16 w-16 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
        </svg>
    </div>

    <!-- Header -->
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-white">Welcome Back</h2>
        <p class="text-slate-400 mt-2">Sign in to continue to your account</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4 p-4 rounded-lg bg-emerald-900/30 text-emerald-400 border border-emerald-800" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-5">
            <label for="email" class="block text-sm font-medium text-slate-300 mb-2">
                {{ __('Email Address') }}
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-violet-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                    </svg>
                </div>
                <input id="email" 
                    type="email" 
                    name="email" 
                    value="{{ old('email') }}"
                    class="block w-full pl-10 pr-4 py-3 border-2 border-slate-500 rounded-xl text-slate-900 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 input-focus-ring transition duration-200 font-medium"
                    placeholder="you@example.com"
                    required 
                    autofocus 
                    autocomplete="username" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror/>
            </div>
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2 text-sm text-red-400" />
        </div>

        <!-- Password -->
        <div class="mb-5">
            <label for="password" class="block text-sm font-medium text-slate-300 mb-2">
                {{ __('Password') }}
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-violet-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input id="password" 
                    type="password" 
                    name="password"
                    class="block w-full pl-10 pr-4 py-3 border-2 border-slate-500 rounded-xl text-slate-900 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 input-focus-ring transition duration-200 font-medium"
                    placeholder="••••••••"
                    required 
                    autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror/>
            </div>
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2 text-sm text-red-400" />
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between mb-6">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" 
                    type="checkbox" 
                    class="w-4 h-4 rounded border-slate-600 text-violet-600 bg-slate-700 focus:ring-violet-500 focus:ring-offset-slate-800 transition duration-200" 
                    name="remember">
                <span class="ml-2 text-sm text-slate-400">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-violet-400 hover:text-violet-300 transition duration-200" 
                   href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <!-- Submit Button -->
        <button type="submit" 
            class="w-full btn-gradient text-white font-semibold py-3 px-4 rounded-xl focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-800 focus:ring-violet-500 transition duration-200">
            {{ __('Sign In') }}
        </button>

        <!-- Register Link -->
        @if (Route::has('register'))
            <p class="mt-6 text-center text-sm text-slate-400">
                {{ __("Don't have an account?") }}
                <a href="{{ route('register') }}" class="font-medium text-violet-400 hover:text-violet-300 transition duration-200">
                    {{ __('Sign up') }}
                </a>
            </p>
        @endif
    </form>
</x-guest-layout>
