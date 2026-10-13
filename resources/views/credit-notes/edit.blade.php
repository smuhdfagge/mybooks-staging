<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Edit credit note {{ $creditNote->credit_note_number }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <form action="{{ route('credit-notes.update', $creditNote) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                @include('credit-notes._form')
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('credit-notes.show', $creditNote) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    <button type="submit" class="btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
