<x-guest-layout>
    <!-- Logo -->
    <div class="flex justify-center mb-6">
        <svg class="h-16 w-16 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
    </div>

    <!-- Header -->
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-white">Two-Factor Authentication</h2>
        <p class="text-slate-400 mt-2">Enter the code from your authenticator app to continue</p>
    </div>

    @if (session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-900/30 text-red-400 border border-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div x-data="{ useRecovery: false }">
        {{-- TOTP Code Form --}}
        <form method="POST" action="{{ route('two-factor.verify') }}" x-show="!useRecovery">
            @csrf
            <div class="mb-5">
                <label for="code" class="block text-sm font-medium text-slate-300 mb-2">
                    Authentication Code
                </label>
                <input id="code"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    class="block w-full px-4 py-3 border-2 border-slate-500 rounded-xl text-slate-900 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition duration-200 font-mono text-center text-2xl tracking-widest"
                    placeholder="000000"
                    maxlength="6"
                    required
                    autofocus />
                @error('code')
                    <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full py-3 px-4 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg shadow-violet-500/25">
                Verify
            </button>
        </form>

        {{-- Recovery Code Form --}}
        <form method="POST" action="{{ route('two-factor.verify') }}" x-show="useRecovery" x-cloak>
            @csrf
            <div class="mb-5">
                <label for="recovery_code" class="block text-sm font-medium text-slate-300 mb-2">
                    Recovery Code
                </label>
                <input id="recovery_code"
                    type="text"
                    name="recovery_code"
                    class="block w-full px-4 py-3 border-2 border-slate-500 rounded-xl text-slate-900 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition duration-200 font-mono text-center tracking-widest"
                    placeholder="XXXX-XXXX"
                    required />
                @error('recovery_code')
                    <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full py-3 px-4 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg shadow-violet-500/25">
                Verify Recovery Code
            </button>
        </form>

        <div class="mt-4 text-center">
            <button type="button"
                @click="useRecovery = !useRecovery"
                class="text-sm text-violet-400 hover:text-violet-300 transition">
                <span x-show="!useRecovery">Use a recovery code instead</span>
                <span x-show="useRecovery">Use authenticator app instead</span>
            </button>
        </div>
    </div>
</x-guest-layout>
