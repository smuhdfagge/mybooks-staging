{{-- Admin: the people who run MyBooks admin (tables plan T5). --}}
@php
    $roles = ['super_admin' => 'Super admin', 'admin' => 'Admin', 'viewer' => 'Can only look'];
    $me = auth('admin')->id();
@endphp
<x-layouts.admin>
    <x-slot name="header">Admin team</x-slot>

    <div class="space-y-3">
        <x-table.page-header title="Admin team" description="The people who can sign in to MyBooks admin, and what each can do.">
            <x-slot name="actions">
                <a href="{{ route('admin.users.create') }}" class="btn-new">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                    New admin
                </a>
            </x-slot>
        </x-table.page-header>

        <x-table caption="Admin team" class="hidden md:block">
            <x-slot name="head">
                <x-table.th>Name</x-table.th>
                <x-table.th>Email</x-table.th>
                <x-table.th>Role</x-table.th>
                <x-table.th>Added</x-table.th>
                <x-table.th>Status</x-table.th>
                <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
            </x-slot>
            @foreach ($adminUsers as $adminUser)
                <tr>
                    <td>
                        <a href="{{ route('admin.users.edit', $adminUser) }}" class="tbl-link">{{ $adminUser->name }}</a>@if ($adminUser->id === $me) <span class="tbl-muted">(you)</span>@endif
                    </td>
                    <td class="tbl-muted">{{ $adminUser->email }}</td>
                    <td>{{ $roles[$adminUser->role] ?? \Illuminate\Support\Str::headline((string) $adminUser->role) }}</td>
                    <td class="tbl-muted whitespace-nowrap">{{ $adminUser->created_at->format('j M Y') }}</td>
                    <td><x-status-badge :status="$adminUser->is_active ? 'active' : 'inactive'" /></td>
                    <td class="tbl-menu">
                        <x-table.dropdown :sr-label="'Actions for '.$adminUser->name">
                            <x-table.menu-item :href="route('admin.users.edit', $adminUser)">Edit</x-table.menu-item>
                            @if ($adminUser->id !== $me)
                                <x-table.menu-item :post="route('admin.users.toggle-status', $adminUser)" method="PATCH">{{ $adminUser->is_active ? 'Make inactive' : 'Make active' }}</x-table.menu-item>
                                <x-table.menu-item :post="route('admin.users.destroy', $adminUser)" method="DELETE" :confirm="'Delete '.$adminUser->name.'? They will no longer be able to sign in to admin.'" danger>Delete</x-table.menu-item>
                            @endif
                        </x-table.dropdown>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <ul class="space-y-2 md:hidden" aria-label="Admin team">
            @foreach ($adminUsers as $adminUser)
                <li>
                    <x-table.card :href="route('admin.users.edit', $adminUser)" :title="$adminUser->name" :meta="($roles[$adminUser->role] ?? $adminUser->role).' · '.$adminUser->email">
                        @unless ($adminUser->is_active)
                            <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                        @endunless
                    </x-table.card>
                </li>
            @endforeach
        </ul>
        <x-table.footer :rows="$adminUsers" links />
    </div>
</x-layouts.admin>
