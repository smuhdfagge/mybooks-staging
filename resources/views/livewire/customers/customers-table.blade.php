{{-- Customers list (tables plan T2). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $canBulk = $user->canAny(['edit customers', 'delete customers']);
    $ids = $customers->pluck('id')->map(fn ($id) => (string) $id)->all();
    $statements = \App\Http\Middleware\EnsureFeatureEnabled::enabled('statements');
    $canSend = $statements && $user->can('send invoices');
    $sendButton = 'inline-flex h-9 items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 text-[13px] font-medium text-gray-800 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700';
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    @if ($canSend)
        @include('statements.partials.bulk-send', ['side' => 'customers', 'selected' => $selectedItems, 'noButton' => true])
    @endif

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name, company, email or phone" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canSend)
            <x-slot name="end">
                <button type="button" data-open-modal="bulk-statements" class="{{ $sendButton }}">Email statements</button>
            </x-slot>
        @endif
        @if ($canBulk || $canSend)
            <x-slot name="bulk">
                @if ($canSend)<button type="button" data-open-modal="bulk-statements" class="{{ $sendButton }}">Email statements</button>@endif
                @can('edit customers')
                    <x-table.bulk-button action="activate">Make active</x-table.bulk-button>
                    <x-table.bulk-button action="deactivate" confirm="Make the ticked customers inactive? They stay in your records but drop out of pick lists.">Make inactive</x-table.bulk-button>
                @endcan
                @can('delete customers')<x-table.bulk-button action="delete" danger confirm="Delete the ticked customers? Customers with invoices, payments or other records are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$customers" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($customers->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No customers match these filters" />
                @else
                    <x-table.empty title="No customers yet" text="Add the people and businesses you sell to. You'll see what each one owes you here.">
                        @can('create customers')<a href="{{ route('customers.create') }}" class="btn-new">New customer</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Customers" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk || $canSend)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every customer on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th>Email</x-table.th>
                    <x-table.th>Phone</x-table.th>
                    <x-table.th field="outstanding_balance" :sort="[$sortField, $sortDirection]" num>Owes you</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($customers as $customer)
                    @php
                        $ticked = in_array((string) $customer->id, $selectedItems, true);
                        $owes = (float) $customer->outstanding_balance;
                    @endphp
                    <tr wire:key="cu-{{ $customer->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk || $canSend)<x-table.check :id="$customer->id" :label="$customer->name" />@endif
                        <td class="max-w-[18rem]">
                            <a href="{{ route('customers.show', $customer) }}" class="tbl-link block truncate">{{ $customer->name }}</a>
                            @if ($customer->company_name && $customer->company_name !== $customer->name)<div class="truncate text-xs tbl-muted">{{ $customer->company_name }}</div>@endif
                        </td>
                        <td class="max-w-[16rem] truncate {{ $customer->email ? 'tbl-muted' : 'tbl-zero' }}">{{ $customer->email ?: '—' }}</td>
                        <td class="{{ $customer->phone ? 'tbl-muted' : 'tbl-zero' }}">{{ $customer->phone ?: '—' }}</td>
                        <td class="num {{ $owes > 0 ? '' : 'tbl-zero' }}">{{ $owes > 0 ? $money($owes) : '—' }}</td>
                        <td><x-status-badge :status="$customer->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$customer->name">
                                <x-table.menu-item :href="route('customers.show', $customer)">View</x-table.menu-item>
                                @if ($statements)
                                    <x-table.menu-item :href="route('customers.statement', $customer)">Statement</x-table.menu-item>
                                @endif
                                @can('create invoices')
                                    <x-table.menu-item :href="route('invoices.create', ['customer_id' => $customer->id])">New invoice</x-table.menu-item>
                                @endcan
                                @can('edit customers')
                                    <x-table.menu-item :href="route('customers.edit', $customer)">Edit</x-table.menu-item>
                                @endcan
                                @can('delete customers')
                                    <x-table.menu-item wire="deleteCustomer({{ $customer->id }})" :confirm="'Delete '.$customer->name.'? This can\'t be undone.'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk || $canSend)<td></td>@endif
                        <td colspan="3">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'customer' : 'customers' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->owed) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Customers">
                @foreach ($customers as $customer)
                    @php $owes = (float) $customer->outstanding_balance; @endphp
                    <li wire:key="cu-card-{{ $customer->id }}">
                        <x-table.card :href="route('customers.show', $customer)" :title="$customer->name"
                            :amount="$owes > 0 ? \App\Support\Money::format($owes) : null"
                            :meta="$customer->email ?: ($customer->phone ?: ($customer->company_name ?: ''))">
                            @if (! $customer->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">{{ \App\Support\Money::format($totals->owed) }} owed to you</p>
        @endif
    </div>

    <x-table.footer :rows="$customers" />
</div>
