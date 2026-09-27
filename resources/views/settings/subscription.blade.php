<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Subscription') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Manage your subscription plan and billing
                </p>
            </div>
        </div>
    </x-slot>

    <livewire:subscriptions.subscription-manager />
</x-app-layout>
