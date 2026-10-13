<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Edit invoice template {{ $template->name }}</x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <livewire:settings.invoice-template-editor :template="$template" />
        </div>
    </div>
</x-app-layout>
