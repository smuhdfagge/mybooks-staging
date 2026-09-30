<x-layouts.admin>
    <x-slot name="header">Recovery Codes</x-slot>

    <div class="max-w-xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Save your recovery codes</h1>
            <p class="text-sm text-amber-700 dark:text-amber-400 mb-6">
                Two-factor authentication is now on. Store these codes somewhere safe: this is the only time they
                are shown. Each code works once, if you lose your authenticator app.
            </p>

            <div class="grid grid-cols-2 gap-3 mb-6">
                @foreach ($recoveryCodes as $code)
                    <div class="font-mono text-sm bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-3 py-2 rounded border border-gray-200 dark:border-gray-700 text-center select-all">{{ $code }}</div>
                @endforeach
            </div>

            <a href="{{ route('admin.tenants.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                I have saved them, continue
            </a>
        </div>
    </div>
</x-layouts.admin>
