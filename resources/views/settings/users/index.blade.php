{{-- Users (tables plan T5): who can sign in to this business, and what they can do. --}}
@php
    $max = $plan?->max_users;
    $left = $max !== null ? max(0, $max - $userCount) : null;
    $line = 'The people who can sign in to your business, and the roles that set what they can do.';
    if ($max !== null) {
        $line .= " {$userCount} of {$max} users".($left > 0 ? ", {$left} ".\Illuminate\Support\Str::plural('place', $left).' left.' : '. Your plan is full.');
    }
    $plus = '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Users" :description="$line">
            <x-slot name="more">
                @can('view roles')<x-table.menu-item :href="route('settings.roles')">Roles</x-table.menu-item>@endcan
                @can('view settings')<x-table.menu-item :href="route('activity-logs.index')">Activity log</x-table.menu-item>@endcan
            </x-slot>
            <x-slot name="actions">
                @can('create users')
                    @if ($canAddUsers)
                        <a href="{{ route('settings.users.create') }}" class="btn-new">{!! $plus !!} New user</a>
                    @else
                        <a href="{{ route('settings.subscription') }}" class="btn-new">Upgrade to add users</a>
                    @endif
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-3">
        <x-table caption="Users" class="hidden md:block">
            <x-slot name="head">
                <x-table.th>Name</x-table.th>
                <x-table.th>Email</x-table.th>
                <x-table.th>Roles</x-table.th>
                <x-table.th>Status</x-table.th>
                <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
            </x-slot>
            @foreach ($users as $user)
                @php $me = $user->id === auth()->id(); @endphp
                <tr>
                    <td>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</span>@if ($me) <span class="tbl-muted">(you)</span>@endif
                        @if ($user->phone)<div class="text-xs tbl-muted">{{ $user->phone }}</div>@endif
                    </td>
                    <td class="tbl-muted">{{ $user->email }}</td>
                    <td class="{{ $user->roles->isEmpty() ? 'tbl-zero' : '' }}">{{ $user->roles->pluck('name')->map(fn ($r) => ucfirst($r))->join(', ') ?: 'No role' }}</td>
                    <td><x-status-badge :status="($user->is_active ?? true) ? 'active' : 'inactive'" /></td>
                    <td class="tbl-menu">
                        @canany(['edit users', 'delete users'])
                            <x-table.dropdown :sr-label="'Actions for '.$user->name">
                                @can('edit users')<x-table.menu-item :href="route('settings.users.edit', $user)">Edit</x-table.menu-item>@endcan
                                @if (! $me)
                                    @can('delete users')<x-table.menu-item :post="route('settings.users.destroy', $user)" method="DELETE" :confirm="'Delete '.$user->name.'? They will no longer be able to sign in.'" danger>Delete</x-table.menu-item>@endcan
                                @endif
                            </x-table.dropdown>
                        @endcanany
                    </td>
                </tr>
            @endforeach
        </x-table>
        <ul class="space-y-2 md:hidden" aria-label="Users">
            @foreach ($users as $user)
                <li>
                    <x-table.card :href="auth()->user()->can('edit users') ? route('settings.users.edit', $user) : null" :title="$user->name" :meta="$user->email.($user->roles->isNotEmpty() ? ' · '.$user->roles->pluck('name')->map(fn ($r) => ucfirst($r))->join(', ') : '')">
                        @if (! ($user->is_active ?? true))
                            <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                        @endif
                    </x-table.card>
                </li>
            @endforeach
        </ul>
        <x-table.footer :rows="$users" links />
    </div>
</x-app-layout>
