<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Activity log" description="Who did what in your business, newest first.">
            <x-slot name="more">
                @can('export reports')<x-table.menu-item :href="route('exports.index')">Exports</x-table.menu-item>@endcan
                @can('view users')<x-table.menu-item :href="route('settings.users')">Users</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('export reports')<a href="{{ route('exports.backup') }}" class="btn-new">Full backup</a>@endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:activity-logs.activity-logs-table />
</x-app-layout>
