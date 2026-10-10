{{-- Admin: all businesses (tables plan T5). --}}
@php
    $filtered = request()->filled('search') || request()->filled('plan');
    $subLabel = ['active' => 'Paying', 'trialing' => 'On trial', 'past_due' => 'Payment late', 'cancelled' => 'Cancelled', 'expired' => 'Expired'];
    $subTone = ['active' => 'active', 'trialing' => 'pending', 'past_due' => 'overdue', 'cancelled' => 'cancelled', 'expired' => 'expired'];
@endphp
<x-layouts.admin>
    <x-slot name="header">All tenants</x-slot>

    <div class="space-y-3">
        <x-table.page-header title="All tenants" description="Every business on MyBooks, its plan and when it renews." />

        <x-table.tabs :tabs="$tabs" :active="$status" />

        <x-table.query-bar :action="route('admin.tenants.list')" placeholder="Search name or email" :filtered="$filtered" :keep="['status']">
            <x-slot name="filters">
                <x-table.query-select name="plan" label="Plan" :options="$plans->pluck('name', 'id')" />
            </x-slot>
        </x-table.query-bar>

        @if ($tenants->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $status !== '')
                    <x-table.empty filtered title="No businesses match" :clear="route('admin.tenants.list')" />
                @else
                    <x-table.empty title="No businesses yet" text="Businesses show here once someone signs up." />
                @endif
            </div>
        @else
            <x-table caption="Businesses" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Business</x-table.th>
                    <x-table.th>Plan</x-table.th>
                    <x-table.th>Subscription</x-table.th>
                    <x-table.th>Renews or ends</x-table.th>
                    <x-table.th num>Users</x-table.th>
                    <x-table.th>Account</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($tenants as $tenant)
                    @php
                        $sub = $tenant->activeSubscription;
                        $ends = $sub?->ends_at;
                        $late = $ends && $ends->isPast();
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('admin.tenants.show', $tenant) }}" class="tbl-link">{{ $tenant->name }}</a>
                            <div class="text-xs tbl-muted">{{ $tenant->email }}</div>
                        </td>
                        <td class="{{ $sub?->plan ? '' : 'tbl-zero' }}">{{ $sub?->plan?->name ?? '—' }}@if ($sub?->billing_cycle)<div class="text-xs tbl-muted">{{ ucfirst($sub->billing_cycle) }}</div>@endif</td>
                        <td>
                            @if ($sub)
                                <x-status-badge :status="$subTone[$sub->status] ?? 'draft'" :label="$subLabel[$sub->status] ?? ucfirst($sub->status)" />
                            @else
                                <span class="tbl-zero">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap {{ $late ? 'tbl-late' : ($ends ? 'tbl-muted' : 'tbl-zero') }}">{{ $ends ? $ends->format('j M Y') : '—' }}</td>
                        <td class="num">{{ number_format($tenant->users_count) }}</td>
                        <td><x-status-badge :status="$tenant->is_active ? 'active' : 'inactive'" :label="$tenant->is_active ? 'Open' : 'Turned off'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$tenant->name">
                                <x-table.menu-item :href="route('admin.tenants.show', $tenant)">View</x-table.menu-item>
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Businesses">
                @foreach ($tenants as $tenant)
                    @php $sub = $tenant->activeSubscription; @endphp
                    <li>
                        <x-table.card :href="route('admin.tenants.show', $tenant)" :title="$tenant->name"
                            :meta="($sub?->plan?->name ?? 'No plan').($sub?->ends_at ? ' · ends '.$sub->ends_at->format('j M Y') : '')"
                            :tone="$tenant->is_active ? 'muted' : 'bad'">
                            @if ($sub)
                                <x-slot name="badge"><x-status-badge :status="$subTone[$sub->status] ?? 'draft'" :label="$subLabel[$sub->status] ?? ucfirst($sub->status)" /></x-slot>
                            @endif
                            @unless ($tenant->is_active)
                                <x-slot name="alert">Account turned off</x-slot>
                            @endunless
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-table.footer :rows="$tenants" links />
    </div>
</x-layouts.admin>
