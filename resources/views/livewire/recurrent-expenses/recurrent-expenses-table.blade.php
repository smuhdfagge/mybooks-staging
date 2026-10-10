{{-- Recurrent expenses list (tables plan T3): expenses MyBooks makes on a schedule. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $late = fn ($p) => $p->status === 'active' && $p->next_expense_date && $p->next_expense_date->lt(today());
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name, description or vendor" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.pick model="account" label="Account" :options="$accounts" />
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($profiles->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No recurrent expenses match these filters" />
                @else
                    <x-table.empty title="No recurrent expenses yet" text="Transport, airtime, tea: set an expense up once and MyBooks records it on the day.">
                        @can('create recurrent-expenses')<a href="{{ route('recurrent-expenses.create') }}" class="btn-new">New recurrent expense</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Recurrent expenses" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="profile_name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th>Account</x-table.th>
                    <x-table.th>How often</x-table.th>
                    <x-table.th field="next_expense_date" :sort="[$sortField, $sortDirection]">Next expense</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($profiles as $profile)
                    <tr wire:key="rp-{{ $profile->id }}">
                        <td class="max-w-[18rem]">
                            <a href="{{ route('recurrent-expenses.show', $profile) }}" class="tbl-link block truncate">{{ $profile->profile_name }}</a>
                            @if ($profile->vendor)<div class="truncate text-xs tbl-muted">{{ $profile->vendor->name }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $profile->expenseAccount?->name ?? '—' }}</td>
                        <td>{{ ucfirst($profile->frequency) }}</td>
                        <td class="{{ $late($profile) ? 'tbl-late' : 'tbl-muted' }}">{{ $profile->status === 'stopped' ? '—' : $date($profile->next_expense_date) }}</td>
                        <td class="num">{{ $money($profile->total) }}</td>
                        <td><x-status-badge :status="$profile->status" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$profile->profile_name">
                                <x-table.menu-item :href="route('recurrent-expenses.show', $profile)">View</x-table.menu-item>
                                @can('edit recurrent-expenses')
                                    <x-table.menu-item :href="route('recurrent-expenses.edit', $profile)">Edit</x-table.menu-item>
                                    @if ($profile->status !== 'stopped')
                                        <x-table.menu-item wire="toggleOne({{ $profile->id }})">{{ $profile->status === 'active' ? 'Pause' : 'Start again' }}</x-table.menu-item>
                                    @endif
                                @endcan
                                @can('delete recurrent-expenses')
                                    <x-table.menu-item wire="deleteOne({{ $profile->id }})" :confirm="'Delete '.$profile->profile_name.'? Expenses it already made are kept.'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="4">{{ number_format($totals->n) }} {{ $totals->n == 1 ? 'profile' : 'profiles' }}. Active ones come to about this much a month:</td>
                        <td class="num">{{ $money($totals->per_month) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Recurrent expenses">
                @foreach ($profiles as $profile)
                    <li wire:key="rp-card-{{ $profile->id }}">
                        <x-table.card :href="route('recurrent-expenses.show', $profile)" :title="$profile->profile_name" :amount="\App\Support\Money::format($profile->total)"
                            :meta="ucfirst($profile->frequency).($profile->status === 'stopped' ? '' : ' · next '.$date($profile->next_expense_date))" :tone="$late($profile) ? 'bad' : 'muted'">
                            @if ($profile->status !== 'active')
                                <x-slot name="badge"><x-status-badge :status="$profile->status" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Active ones: about {{ \App\Support\Money::format($totals->per_month) }} a month</p>
        @endif
    </div>

    <x-table.footer :rows="$profiles" />
</div>
