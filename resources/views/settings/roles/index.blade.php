{{-- Roles (tables plan T5): what each kind of user can do. --}}
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Roles" description="What each kind of user can see and do. MyBooks' own roles can be copied and changed.">
            <x-slot name="more">
                @can('view users')<x-table.menu-item :href="route('settings.users')">Users</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create roles')
                    <a href="{{ route('settings.roles.create') }}" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        New role
                    </a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        @if ($roles->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No roles yet" text="Roles set what each user can see and do." />
            </div>
        @else
            <x-table caption="Roles" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Role</x-table.th>
                    <x-table.th>Made by</x-table.th>
                    <x-table.th num>Permissions</x-table.th>
                    <x-table.th num>Users</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($roles as $role)
                    <tr>
                        <td class="font-medium text-gray-900 dark:text-white">{{ ucfirst($role->name) }}</td>
                        <td class="tbl-muted">{{ $role->isGlobal() ? 'MyBooks' : 'You' }}</td>
                        <td class="num {{ $role->permissions_count ? '' : 'tbl-zero' }}">{{ $role->permissions_count ?: '—' }}</td>
                        <td class="num {{ $role->users_count ? '' : 'tbl-zero' }}">{{ $role->users_count ?: '—' }}</td>
                        <td class="tbl-menu">
                            @canany(['edit roles', 'delete roles'])
                                <x-table.dropdown :sr-label="'Actions for '.$role->name">
                                    @can('edit roles')<x-table.menu-item :href="route('settings.roles.edit', $role)">{{ $role->isGlobal() ? 'Copy and change' : 'Edit' }}</x-table.menu-item>@endcan
                                    @if (! $role->isGlobal() && ! $role->users_count)
                                        @can('delete roles')<x-table.menu-item :post="route('settings.roles.destroy', $role)" method="DELETE" :confirm="'Delete the '.$role->name.' role?'" danger>Delete</x-table.menu-item>@endcan
                                    @endif
                                </x-table.dropdown>
                            @endcanany
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Roles">
                @foreach ($roles as $role)
                    <li>
                        <x-table.card :href="route('settings.roles.edit', $role)" :title="ucfirst($role->name)" :meta="$role->permissions_count.' permissions · '.$role->users_count.' '.\Illuminate\Support\Str::plural('user', $role->users_count)">
                            <x-slot name="badge"><span class="text-xs tbl-muted">{{ $role->isGlobal() ? 'MyBooks' : 'Yours' }}</span></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-table.footer :rows="$roles" links />
    </div>
</x-app-layout>
