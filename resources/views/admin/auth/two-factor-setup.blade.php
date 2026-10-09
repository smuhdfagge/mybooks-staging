<x-layouts.admin>
    <x-slot name="header">Two-Factor Authentication</x-slot>

    <div class="max-w-xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Set up two-factor authentication</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Two-factor authentication is required for every admin account. Scan the QR code with an
                authenticator app (Google Authenticator, Authy or similar), then enter the 6-digit code it shows.
            </p>

            @if (session('error'))
                <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex flex-col items-center mb-6">
                <div class="bg-white p-4 rounded-lg mb-4">
                    {!! $qrCodeSvg !!}
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Or enter this key manually:</p>
                <code class="text-sm font-mono bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-3 py-1 rounded select-all">{{ $secret }}</code>
            </div>

            <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="max-w-xs mx-auto">
                @csrf
                <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Verification code</label>
                <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
                       class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white text-center font-mono text-xl tracking-widest focus:ring-brand-500 focus:border-brand-500"
                       placeholder="000000">
                @error('code')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
                <button type="submit" class="mt-4 w-full py-3 px-4 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg transition shadow-sm">
                    Turn on two-factor authentication
                </button>
            </form>
        </div>
    </div>
</x-layouts.admin>
