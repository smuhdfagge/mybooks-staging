<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New credit note</h2>
            <a href="{{ $invoice ? route('invoices.show', $invoice) : route('credit-notes.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">A credit note reduces what a customer owes you, for example for returned or faulty goods or a price mistake. It is saved as a draft; opening it posts it to your accounts.</p>
            <form action="{{ route('credit-notes.store') }}" method="POST" class="space-y-6">
                @csrf
                @include('credit-notes._form')
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('credit-notes.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    <button type="submit" class="btn-primary">Save credit note</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
