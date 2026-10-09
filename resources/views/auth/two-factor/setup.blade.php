<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Two-Factor Authentication
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if (session('error'))
                        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($isEnabled)
                        {{-- 2FA is already enabled --}}
                        <div class="text-center">
                            <div class="flex justify-center mb-4">
                                <svg class="h-16 w-16 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-green-600 dark:text-green-400 mb-2">
                                Two-Factor Authentication is Enabled
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">
                                Your account is protected with two-factor authentication.
                            </p>

                            <div class="flex justify-center space-x-4">
                                <a href="{{ route('two-factor.recovery-codes') }}"
                                    class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                    View Recovery Codes
                                </a>

                                <form method="POST" action="{{ route('two-factor.disable') }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <div x-data="{ showPassword: false }">
                                        <button type="button" @click="showPassword = true"
                                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-red-700 transition"
                                            x-show="!showPassword">
                                            Disable 2FA
                                        </button>

                                        <div x-show="showPassword" x-cloak class="flex items-center space-x-2">
                                            <input type="password" name="password" placeholder="Confirm password"
                                                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700" required />
                                            <button type="submit"
                                                class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
                                                Confirm
                                            </button>
                                            <button type="button" @click="showPassword = false"
                                                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        {{-- Setup 2FA --}}
                        <h3 class="text-lg font-semibold mb-4">Set Up Two-Factor Authentication</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            Scan the QR code below with your authenticator app (Google Authenticator, Authy, or similar),
                            then enter the 6-digit verification code to enable 2FA.
                        </p>

                        <div class="flex flex-col items-center mb-6">
                            <div class="bg-white p-4 rounded-lg mb-4">
                                {!! $qrCodeSvg !!}
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Or enter this code manually:</p>
                                <code class="text-sm font-mono bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded select-all">
                                    {{ $secret }}
                                </code>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="max-w-xs mx-auto">
                            @csrf
                            <div class="mb-4">
                                <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Verification Code
                                </label>
                                <input id="code"
                                    type="text"
                                    name="code"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-center font-mono text-xl tracking-widest focus:ring-brand-500 focus:border-brand-500"
                                    placeholder="000000"
                                    maxlength="6"
                                    required
                                    autofocus @error('code') aria-invalid="true" aria-describedby="code-error" @enderror/>
                                @error('code')
                                    <p id="code-error" class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit"
                                class="w-full py-3 px-4 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg transition shadow-sm">
                                Enable Two-Factor Authentication
                            </button>
                        </form>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
