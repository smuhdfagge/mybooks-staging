{{-- The active count moved here from the dashboard (dashboard upgrade). --}}
@php $working = \App\Models\Employee::where('status', 'active')->count(); @endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Employees" :description="'Your staff, where they work and whether they are at work. '.($working === 1 ? '1 active employee.' : $working.' active employees.')">
            <x-slot name="more">
                @can('view departments')<x-table.menu-item :href="route('departments.index')">Departments</x-table.menu-item>@endcan
                @can('view designations')<x-table.menu-item :href="route('designations.index')">Designations</x-table.menu-item>@endcan
                @can('view leaves')<x-table.menu-item :href="route('leaves.index')">Leave</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create employees')
                    <a href="{{ route('employees.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New employee
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <livewire:employees.employees-table />
</x-app-layout>
