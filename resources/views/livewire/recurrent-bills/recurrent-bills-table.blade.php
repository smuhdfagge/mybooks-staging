{{-- Recurrent bills list (tables plan T3): bills MyBooks makes on a schedule. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $late = fn ($p) => $p->status === 'active' && $p->next_bill_date && $p->next_bill_date->lt(today());
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or vendor" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.pick model="vendor" label="Vendor" :options="$vendors" />
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($profiles->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No recurrent bills match these filters" />
                @else
                    <x-table.empty title="No recurrent bills yet" text="Rent, internet, security: set a bill up once and MyBooks makes it on the day.">
                        @can('create recurrent-bills')<a href="{{ route('recurrent-bills.create') }}" class="btn-new">New recurrent bill</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Recurrent bills" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="profile_name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th>Vendor</x-table.th>
                    <x-table.th>How often</x-table.th>
                    <x-table.th field="next_bill_date" :sort="[$sortField, $sortDirection]">Next bill</x-table.th>
                    <x-table.th field="total" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($profiles as $profile)
                    <tr wire:key="rp-{{ $profile->id }}">
                        <td class="max-w-[18rem]">
                            <a href="{{ route('recurrent-bills.show', $profile) }}" class="tbl-link block truncate">{{ $profile->profile_name }}</a>
                            
                        </td>
                        <td class="max-w-[16rem] truncate">{{ $profile->vendor?->name ?? '—' }}</td>
                        <td>{{ ucfirst($profile->frequency) }}</td>
                        <td class="{{ $late($profile) ? 'tbl-late' : 'tbl-muted' }}">{{ $profile->status === 'stopped' ? '—' : $date($profile->next_bill_date) }}</td>
                        <td class="num">{{ $money($profile->total) }}</td>
                        <td><x-status-badge :status="$profile->status" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$profile->profile_name">
                                <x-table.menu-item :href="route('recurrent-bills.show', $profile)">View</x-table.menu-item>
                                @can('edit recurrent-bills')
                                    <x-table.menu-item :href="route('recurrent-bills.edit', $profile)">Edit</x-table.menu-item>
                                    @if ($profile->status !== 'stopped')
                                        <x-table.menu-item wire="toggleOne({{ $profile->id }})">{{ $profile->status === 'active' ? 'Pause' : 'Start again' }}</x-table.menu-item>
                                    @endif
                                @endcan
                                @can('delete recurrent-bills')
                                    <x-table.menu-item wire="deleteOne({{ $profile->id }})" :confirm="'Delete '.$profile->profile_name.'? Bills it already made are kept.'" danger>Delete</x-table.menu-item>
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
            <ul class="space-y-2 md:hidden" aria-label="Recurrent bills">
                @foreach ($profiles as $profile)
                    <li wire:key="rp-card-{{ $profile->id }}">
                        <x-table.card :href="route('recurrent-bills.show', $profile)" :title="$profile->profile_name" :amount="\App\Support\Money::format($profile->total)"
                            :meta="ucfirst($profile->frequency).($profile->status === 'stopped' ? '' : ' · next '.$date($profile->next_bill_date))" :tone="$late($profile) ? 'bad' : 'muted'">
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
