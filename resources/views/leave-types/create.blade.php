<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Add leave type" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('leave-types.store') }}" method="POST" class="p-6">
                    @csrf

                    @include('leave-types._form')

                    <div class="flex items-center justify-end gap-4 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('leave-types.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">Cancel</a>
                        <button type="submit" class="btn-primary">Create Leave Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
