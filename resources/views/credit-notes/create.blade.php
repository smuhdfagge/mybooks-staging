<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New credit note</h2>
            <a href="{{ $invoice ? route('invoices.show', $invoice) : route('credit-notes.index') }}" class="text-sm text-brand-600 dark:text-brand-300 hover:underline">Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">A credit note reduces what a customer owes you, for example for returned or faulty goods or a price mistake. Save it as a draft to check it first, or save and post it now.</p>
            <form action="{{ route('credit-notes.store') }}" method="POST" class="space-y-6">
                @csrf
                @include('credit-notes._form')
                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="{{ $invoice ? route('invoices.show', $invoice) : route('credit-notes.index') }}" class="text-sm text-center text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    <button type="submit" name="status" value="draft" class="inline-flex justify-center items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">Save as draft</button>
                    <button type="submit" name="status" value="open" class="btn-primary justify-center">Save and post</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
