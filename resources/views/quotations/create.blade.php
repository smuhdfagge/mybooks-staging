<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="New quotation" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <form action="{{ route('quotations.store') }}" method="POST" class="space-y-6">
                @csrf
                @include('quotations._form')
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('quotations.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    <button type="submit" class="btn-primary">Save quotation</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
