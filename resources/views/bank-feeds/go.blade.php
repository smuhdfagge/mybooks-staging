<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Taking you to your bank" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div x-data x-init="setTimeout(() => window.location.href = @js($url), 600)">
            <x-card class="p-6 text-center">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Opening Mono's secure page, where you pick your bank and log in. MyBooks never sees your login details.
                </p>
                <p class="mt-4">
                    <a href="{{ $url }}" class="btn-primary" rel="noopener">Continue to Mono</a>
                </p>
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">If nothing happens, click the button. <a href="{{ route('bank-feeds.index') }}" class="underline">Cancel</a></p>
            </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
