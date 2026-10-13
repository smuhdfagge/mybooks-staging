<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Designations" description="Job titles and grades, and which department each belongs to.">
            <x-slot name="more">
                @can('view departments')<x-table.menu-item :href="route('departments.index')">Departments</x-table.menu-item>@endcan
                @can('view employees')<x-table.menu-item :href="route('employees.index')">Employees</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create designations')
                    <a href="{{ route('designations.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New designation
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        @if ($designations->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No designations yet" text="Add job titles such as Cashier or Driver, then give one to each employee.">
                    @can('create designations')<a href="{{ route('designations.create') }}" class="btn-new">New designation</a>@endcan
                </x-table.empty>
            </div>
        @else
            <x-table caption="Designations" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Name</x-table.th>
                    <x-table.th>Department</x-table.th>
                    <x-table.th num>Level</x-table.th>
                    <x-table.th num>Employees</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($designations as $designation)
                    <tr>
                        <td><a href="{{ route('designations.show', $designation) }}" class="tbl-link">{{ $designation->name }}</a></td>
                        <td class="{{ $designation->department ? '' : 'tbl-zero' }}">{{ $designation->department?->name ?? '—' }}</td>
                        <td class="num {{ $designation->level ? 'tbl-muted' : 'tbl-zero' }}">{{ $designation->level ?: '—' }}</td>
                        <td class="num {{ $designation->employees_count ? '' : 'tbl-zero' }}">{{ $designation->employees_count ?: '—' }}</td>
                        <td><x-status-badge :status="$designation->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$designation->name">
                                <x-table.menu-item :href="route('designations.show', $designation)">View</x-table.menu-item>
                                @can('edit designations')<x-table.menu-item :href="route('designations.edit', $designation)">Edit</x-table.menu-item>@endcan
                                @if (! $designation->employees_count)
                                    @can('delete designations')<x-table.menu-item :post="route('designations.destroy', $designation)" method="DELETE" :confirm="'Delete '.$designation->name.'?'" danger>Delete</x-table.menu-item>@endcan
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Designations">
                @foreach ($designations as $designation)
                    <li>
                        <x-table.card :href="route('designations.show', $designation)" :title="$designation->name" :meta="($designation->department?->name ?? '').' · '.$designation->employees_count.' employees'">
                            @if (! $designation->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-table.footer :rows="$designations" links />
    </div>
</x-app-layout>
