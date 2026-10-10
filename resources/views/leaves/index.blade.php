<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Leave" description="Annual, sick and other leave your staff have asked for, and what was decided.">
            @can('view leave-types')
                <x-slot name="more">
                    <x-table.menu-item :href="route('leave-types.index')">Leave types</x-table.menu-item>
                </x-slot>
            @endcan
            <x-slot name="actions">
                @can('create leaves')
                    <a href="{{ route('leaves.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        Record leave
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:leaves.leaves-table />
</x-app-layout>
