<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Recovery Codes
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if (session('success'))
                        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (count($recoveryCodes))
                    <div class="mb-6">
                        <div class="flex items-start space-x-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <svg class="h-6 w-6 text-amber-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <div>
                                <p class="font-medium text-amber-800 dark:text-amber-300">Store these recovery codes in a safe place now</p>
                                <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                                    This is the only time they will be shown. Each recovery code can only be used once.
                                    If you lose access to your authenticator app, you can use one of these codes to regain access to your account.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 mb-6">
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($recoveryCodes as $code)
                                <div class="font-mono text-sm bg-white dark:bg-gray-800 px-3 py-2 rounded border border-gray-200 dark:border-gray-700 text-center select-all">
                                    {{ $code }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg text-sm text-gray-700 dark:text-gray-300">
                        <p>You have <strong>{{ $codesLeft }}</strong> unused recovery {{ Str::plural('code', $codesLeft) }}.</p>
                        <p class="mt-2">For your security, recovery codes are only shown once, when they are created. If you have lost them, regenerate a new set below; the old codes will stop working.</p>
                    </div>
                    @endif

                    <div class="flex items-center justify-between">
                        <a href="{{ route('profile.edit') }}"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition">
                            &larr; Back to Profile
                        </a>

                        <form method="POST" action="{{ route('two-factor.regenerate-codes') }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                                data-confirm="This will invalidate your existing recovery codes. Continue?">
                                Regenerate Codes
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
