{{-- Chart of accounts (tables plan T4): a tab per account type, in code order. --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $canBulk = $user->canAny(['edit chart-of-accounts', 'delete chart-of-accounts']);
    $ids = $accounts->pluck('id')->map(fn ($id) => (string) $id)->all();
    $allTypes = ! array_key_exists($tab, \App\Livewire\ChartOfAccounts\ChartOfAccountsTable::LABELS);
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search code, name or description" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit chart-of-accounts')
                    <x-table.bulk-button action="activate">Make active</x-table.bulk-button>
                    <x-table.bulk-button action="deactivate" confirm="Make the ticked accounts inactive? They drop out of pick lists; their history stays.">Make inactive</x-table.bulk-button>
                @endcan
                @can('delete chart-of-accounts')<x-table.bulk-button action="delete" danger confirm="Delete the ticked accounts? Accounts with entries, sub-accounts or that MyBooks needs are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$accounts" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($accounts->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty filtered title="No accounts match these filters" />
            </div>
        @else
            <x-table caption="Chart of accounts" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every account on this page" />@endif
                    <x-table.th field="account_code" :sort="[$sortField, $sortDirection]">Code</x-table.th>
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    @if ($allTypes)<x-table.th>Type</x-table.th>@endif
                    <x-table.th field="current_balance" :sort="[$sortField, $sortDirection]" num>Balance</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($accounts as $account)
                    @php $ticked = in_array((string) $account->id, $selectedItems, true); $bal = (float) $account->current_balance; @endphp
                    <tr wire:key="coa-{{ $account->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$account->id" :label="$account->account_code.' '.$account->name" />@endif
                        <td class="tabular-nums tbl-muted">{{ $account->account_code }}</td>
                        <td class="max-w-[24rem] {{ $account->parent_id ? 'pl-7' : '' }}">
                            <a href="{{ route('chart-of-accounts.show', $account) }}" class="tbl-link block truncate">{{ $account->name }}</a>
                            @if ($account->parent)<div class="truncate text-xs tbl-muted">Part of {{ $account->parent->account_code }} {{ $account->parent->name }}</div>@endif
                        </td>
                        @if ($allTypes)<td class="tbl-muted">{{ $types[$account->type] ?? ucfirst($account->type) }}</td>@endif
                        <td class="num {{ $bal != 0 ? '' : 'tbl-zero' }}">{{ $bal != 0 ? $money($bal) : '—' }}</td>
                        <td>
                            @if (! $account->is_active)
                                <x-status-badge status="inactive" />
                            @elseif ($account->is_system)
                                <span class="text-xs tbl-muted">Built in</span>
                            @else
                                <x-status-badge status="active" />
                            @endif
                        </td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$account->name">
                                <x-table.menu-item :href="route('chart-of-accounts.show', $account)">View entries</x-table.menu-item>
                                @can('edit chart-of-accounts')<x-table.menu-item :href="route('chart-of-accounts.edit', $account)">Edit</x-table.menu-item>@endcan
                                @if (! $account->is_system && $user->can('delete chart-of-accounts'))
                                    <x-table.menu-item wire="deleteOne({{ $account->id }})" :confirm="'Delete '.$account->account_code.' '.$account->name.'?'" danger>Delete</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                @if ($total !== null)
                    <x-slot name="foot">
                        <tr>
                            @if ($canBulk)<td></td>@endif
                            <td colspan="2">Total of {{ strtolower($tabs[$tab]['label']) }}@if ($filtered) <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                            <td class="num">{{ $money($total) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </x-slot>
                @endif
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Accounts">
                @foreach ($accounts as $account)
                    <li wire:key="coa-card-{{ $account->id }}">
                        <x-table.card :href="route('chart-of-accounts.show', $account)" :title="$account->account_code.' '.$account->name"
                            :amount="(float) $account->current_balance != 0 ? \App\Support\Money::format($account->current_balance) : null"
                            :meta="$types[$account->type] ?? ucfirst($account->type)">
                            @if (! $account->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$accounts" />
</div>
