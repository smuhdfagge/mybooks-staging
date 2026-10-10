<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Leave types" description="The leave your staff can take, how many days a year, and whether it is paid.">
            <x-slot name="more">
                @can('view leaves')<x-table.menu-item :href="route('leaves.index')">Leave requests</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create leave-types')
                    <a href="{{ route('leave-types.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New kind of leave
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        @if ($leaveTypes->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No leave types yet" text="Add the types of leave your staff can take, such as annual, sick or maternity leave.">
                    @can('create leave-types')<a href="{{ route('leave-types.create') }}" class="btn-new">New kind of leave</a>@endcan
                </x-table.empty>
            </div>
        @else
            <x-table caption="Leave types" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Name</x-table.th>
                    <x-table.th>Code</x-table.th>
                    <x-table.th num>Days a year</x-table.th>
                    <x-table.th>Paid</x-table.th>
                    <x-table.th>Unused days</x-table.th>
                    <x-table.th num>Requests</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($leaveTypes as $type)
                    <tr>
                        <td><a href="{{ route('leave-types.show', $type) }}" class="tbl-link">{{ $type->name }}</a></td>
                        <td class="{{ $type->code ? 'tbl-muted' : 'tbl-zero' }}">{{ $type->code ?: '—' }}</td>
                        <td class="num">{{ $type->days_per_year }}</td>
                        <td class="tbl-muted">{{ $type->is_paid ? 'Paid' : 'Unpaid' }}</td>
                        <td class="tbl-muted">{{ $type->is_carry_forward ? 'Carried over'.($type->max_carry_forward_days ? ' (up to '.$type->max_carry_forward_days.')' : '') : 'Lost at year end' }}</td>
                        <td class="num {{ $type->leaves_count ? '' : 'tbl-zero' }}">{{ $type->leaves_count ?: '—' }}</td>
                        <td><x-status-badge :status="$type->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$type->name">
                                <x-table.menu-item :href="route('leave-types.show', $type)">View</x-table.menu-item>
                                @can('edit leave-types')<x-table.menu-item :href="route('leave-types.edit', $type)">Edit</x-table.menu-item>@endcan
                                @if (! $type->leaves_count)
                                    @can('delete leave-types')<x-table.menu-item :post="route('leave-types.destroy', $type)" method="DELETE" :confirm="'Delete '.$type->name.'?'" danger>Delete</x-table.menu-item>@endcan
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Leave types">
                @foreach ($leaveTypes as $type)
                    <li>
                        <x-table.card :href="route('leave-types.show', $type)" :title="$type->name" :amount="$type->days_per_year.' days'" :meta="($type->is_paid ? 'Paid' : 'Unpaid').' · '.$type->leaves_count.' requests'">
                            @if (! $type->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-table.footer :rows="$leaveTypes" links />
    </div>
</x-app-layout>
