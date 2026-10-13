<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="New invoice template" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <livewire:settings.invoice-template-editor />
        </div>
    </div>
</x-app-layout>
