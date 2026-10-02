<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit quotation {{ $quotation->quotation_number }}</h2>
            <a href="{{ route('quotations.show', $quotation) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Back to quotation</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(in_array($quotation->status, ['rejected', 'expired'], true))
                <div class="mb-4 p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 text-sm text-yellow-800 dark:text-yellow-200">
                    This quotation is {{ $quotation->status }}. Saving your changes makes it a draft again, ready to send.
                </div>
            @endif
            <form action="{{ route('quotations.update', $quotation) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                @include('quotations._form')
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('quotations.show', $quotation) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    <button type="submit" class="btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
