{{-- Bank and cash accounts list (tables plan T4). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $canBulk = $user->canAny(['edit banks', 'delete banks']);
    $ids = $banks->pluck('id')->map(fn ($id) => (string) $id)->all();
    $home = \App\Support\Money::currency();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search account or bank name" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.pick model="type" label="Type" :options="$types" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit banks')
                    <x-table.bulk-button action="activate">Put back in use</x-table.bulk-button>
                    <x-table.bulk-button action="deactivate">Take out of use</x-table.bulk-button>
                @endcan
                @can('delete banks')<x-table.bulk-button action="delete" danger confirm="Delete the ticked accounts? Accounts with money recorded through them are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$banks" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($banks->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No accounts match these filters" />
                @else
                    <x-table.empty title="No bank accounts yet" text="Add each bank account, till or cash box, so payments go to the right place.">
                        @can('create banks')<a href="{{ route('banks.create') }}" class="btn-new">New account</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Bank accounts" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every account on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Account</x-table.th>
                    <x-table.th>Bank</x-table.th>
                    <x-table.th>Type</x-table.th>
                    <x-table.th field="current_balance" :sort="[$sortField, $sortDirection]" num>Balance</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($banks as $bank)
                    @php $ticked = in_array((string) $bank->id, $selectedItems, true); @endphp
                    <tr wire:key="bk-{{ $bank->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$bank->id" :label="$bank->name" />@endif
                        <td>
                            <a href="{{ route('banks.show', $bank) }}" class="tbl-link">{{ $bank->name }}</a>
                            <div class="text-xs tbl-muted">{{ collect([$bank->is_primary ? 'Main account' : null, $bank->account_number ? $bank->masked_account_number : null])->filter()->implode(' · ') }}</div>
                        </td>
                        <td class="{{ $bank->bank_name ? '' : 'tbl-zero' }}">{{ $bank->bank_name ?: '—' }}</td>
                        <td class="tbl-muted">{{ $types[$bank->account_type] ?? ucfirst((string) $bank->account_type) }}</td>
                        <td class="num">{{ $money($bank->current_balance) }}@if ($bank->currency && $bank->currency !== $home) <span class="tbl-muted">{{ $bank->currency }}</span>@endif</td>
                        <td><x-status-badge :status="$bank->is_active ? 'active' : 'inactive'" :label="$bank->is_active ? 'In use' : 'Not in use'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$bank->name">
                                <x-table.menu-item :href="route('banks.show', $bank)">View</x-table.menu-item>
                                <x-table.menu-item :href="route('banks.transactions', $bank)">Money in and out</x-table.menu-item>
                                @can('reconcile banks')<x-table.menu-item :href="route('banks.reconcile', $bank)">Reconcile</x-table.menu-item>@endcan
                                @can('edit banks')<x-table.menu-item :href="route('banks.edit', $bank)">Edit</x-table.menu-item>@endcan
                                @can('delete banks')
                                    <x-table.menu-item wire="deleteOne({{ $bank->id }})" :confirm="'Delete '.$bank->name.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="3">Total in accounts in use @if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->balance) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Bank accounts">
                @foreach ($banks as $bank)
                    <li wire:key="bk-card-{{ $bank->id }}">
                        <x-table.card :href="route('banks.show', $bank)" :title="$bank->name" :amount="\App\Support\Money::format($bank->current_balance)"
                            :meta="collect([$bank->bank_name, $bank->account_number ? $bank->masked_account_number : null, $types[$bank->account_type] ?? null])->filter()->implode(' · ')">
                            @if (! $bank->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" label="Not in use" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Total {{ \App\Support\Money::format($totals->balance) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$banks" />
</div>
