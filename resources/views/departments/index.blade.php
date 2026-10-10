<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Departments" description="The parts of your business staff belong to, and who runs each.">
            <x-slot name="more">
                @can('view designations')<x-table.menu-item :href="route('designations.index')">Designations</x-table.menu-item>@endcan
                @can('view employees')<x-table.menu-item :href="route('employees.index')">Employees</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create departments')
                    <a href="{{ route('departments.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New department
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        @if ($departments->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No departments yet" text="Add departments such as Sales or Store, then put each employee in one.">
                    @can('create departments')<a href="{{ route('departments.create') }}" class="btn-new">New department</a>@endcan
                </x-table.empty>
            </div>
        @else
            <x-table caption="Departments" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Name</x-table.th>
                    <x-table.th>Code</x-table.th>
                    <x-table.th>Runs it</x-table.th>
                    <x-table.th num>Employees</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($departments as $department)
                    <tr>
                        <td>
                            <a href="{{ route('departments.show', $department) }}" class="tbl-link">{{ $department->name }}</a>
                            @if ($department->parent)<div class="text-xs tbl-muted">Part of {{ $department->parent->name }}</div>@endif
                        </td>
                        <td class="{{ $department->code ? 'tbl-muted' : 'tbl-zero' }}">{{ $department->code ?: '—' }}</td>
                        <td class="{{ $department->manager ? '' : 'tbl-zero' }}">{{ $department->manager?->full_name ?? '—' }}</td>
                        <td class="num {{ $department->employees_count ? '' : 'tbl-zero' }}">{{ $department->employees_count ?: '—' }}</td>
                        <td><x-status-badge :status="$department->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$department->name">
                                <x-table.menu-item :href="route('departments.show', $department)">View</x-table.menu-item>
                                @can('view employees')<x-table.menu-item :href="route('employees.index', ['department' => $department->id])">Its employees</x-table.menu-item>@endcan
                                @can('edit departments')<x-table.menu-item :href="route('departments.edit', $department)">Edit</x-table.menu-item>@endcan
                                @if (! $department->employees_count)
                                    @can('delete departments')<x-table.menu-item :post="route('departments.destroy', $department)" method="DELETE" :confirm="'Delete '.$department->name.'?'" danger>Delete</x-table.menu-item>@endcan
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Departments">
                @foreach ($departments as $department)
                    <li>
                        <x-table.card :href="route('departments.show', $department)" :title="$department->name" :meta="$department->employees_count.' employees'.($department->manager ? ' · '.$department->manager->full_name : '')">
                            @if (! $department->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-table.footer :rows="$departments" links />
    </div>
</x-app-layout>
