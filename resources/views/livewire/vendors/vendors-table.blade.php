{{-- Vendors list (tables plan T3). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $canBulk = $user->canAny(['edit vendors', 'delete vendors']);
    $ids = $vendors->pluck('id')->map(fn ($id) => (string) $id)->all();
    $statements = \App\Http\Middleware\EnsureFeatureEnabled::enabled('statements');
    $canSend = $statements && $user->can('send invoices');
    $ticks = $canBulk || $canSend;
    $sendButton = 'inline-flex h-9 items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 text-[13px] font-medium text-gray-800 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700';
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    @if ($canSend)
        @include('statements.partials.bulk-send', ['side' => 'suppliers', 'selected' => $selectedItems, 'noButton' => true])
    @endif

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name, company, email or phone" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canSend)
            <x-slot name="end">
                <button type="button" data-open-modal="bulk-statements" class="{{ $sendButton }}">Email statements</button>
            </x-slot>
        @endif
        @if ($ticks)
            <x-slot name="bulk">
                @if ($canSend)<button type="button" data-open-modal="bulk-statements" class="{{ $sendButton }}">Email statements</button>@endif
                @can('edit vendors')
                    <x-table.bulk-button action="activate">Make active</x-table.bulk-button>
                    <x-table.bulk-button action="deactivate" confirm="Make the ticked vendors inactive? They stay in your records but drop out of pick lists.">Make inactive</x-table.bulk-button>
                @endcan
                @can('delete vendors')<x-table.bulk-button action="delete" danger confirm="Delete the ticked vendors? Vendors with bills or expenses are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$vendors" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($vendors->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No vendors match these filters" />
                @else
                    <x-table.empty title="No vendors yet" text="Add the businesses you buy from. You'll see what you owe each one here.">
                        @can('create vendors')<a href="{{ route('vendors.create') }}" class="btn-new">New vendor</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Vendors" class="hidden md:block">
                <x-slot name="head">
                    @if ($ticks)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every vendor on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th>Email</x-table.th>
                    <x-table.th>Phone</x-table.th>
                    <x-table.th field="total_purchases" :sort="[$sortField, $sortDirection]" num>Bought</x-table.th>
                    <x-table.th field="outstanding_balance" :sort="[$sortField, $sortDirection]" num>You owe</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($vendors as $vendor)
                    @php
                        $ticked = in_array((string) $vendor->id, $selectedItems, true);
                        $owe = (float) $vendor->outstanding_balance;
                        $bought = (float) $vendor->total_purchases;
                    @endphp
                    <tr wire:key="ve-{{ $vendor->id }}" @if ($ticked) data-picked @endif>
                        @if ($ticks)<x-table.check :id="$vendor->id" :label="$vendor->name" />@endif
                        <td class="max-w-[18rem]">
                            <a href="{{ route('vendors.show', $vendor) }}" class="tbl-link block truncate">{{ $vendor->name }}</a>
                            @if ($vendor->company_name && $vendor->company_name !== $vendor->name)<div class="truncate text-xs tbl-muted">{{ $vendor->company_name }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate {{ $vendor->email ? 'tbl-muted' : 'tbl-zero' }}">{{ $vendor->email ?: '—' }}</td>
                        <td class="{{ $vendor->phone ? 'tbl-muted' : 'tbl-zero' }}">{{ $vendor->phone ?: '—' }}</td>
                        <td class="num {{ $bought > 0 ? '' : 'tbl-zero' }}">{{ $bought > 0 ? $money($bought) : '—' }}</td>
                        <td class="num {{ $owe > 0 ? '' : 'tbl-zero' }}">{{ $owe > 0 ? $money($owe) : '—' }}</td>
                        <td><x-status-badge :status="$vendor->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$vendor->name">
                                <x-table.menu-item :href="route('vendors.show', $vendor)">View</x-table.menu-item>
                                @if ($statements)
                                    <x-table.menu-item :href="route('vendors.statement', $vendor)">Statement</x-table.menu-item>
                                @endif
                                @can('create bills')
                                    <x-table.menu-item :href="route('bills.create', ['vendor_id' => $vendor->id])">New bill</x-table.menu-item>
                                @endcan
                                @can('edit vendors')
                                    <x-table.menu-item :href="route('vendors.edit', $vendor)">Edit</x-table.menu-item>
                                @endcan
                                @can('delete vendors')
                                    <x-table.menu-item wire="deleteOne({{ $vendor->id }})" :confirm="'Delete '.$vendor->name.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($ticks)<td></td>@endif
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'vendor' : 'vendors' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->owed) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Vendors">
                @foreach ($vendors as $vendor)
                    @php $owe = (float) $vendor->outstanding_balance; @endphp
                    <li wire:key="ve-card-{{ $vendor->id }}">
                        <x-table.card :href="route('vendors.show', $vendor)" :title="$vendor->name"
                            :amount="$owe > 0 ? \App\Support\Money::format($owe) : null"
                            :meta="$vendor->email ?: ($vendor->phone ?: ($vendor->company_name ?: ''))">
                            @if (! $vendor->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">You owe {{ \App\Support\Money::format($totals->owed) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$vendors" />
</div>
