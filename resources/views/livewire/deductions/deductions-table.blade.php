{{-- Deductions (tables plan T5): ready-made lines to add to salary structures. --}}
@php
    $canBulk = auth()->user()->can('create payroll');
    $ids = $rows->pluck('id')->map(fn ($id) => (string) $id)->all();
    $amount = fn ($r) => $r->amount_type === 'percentage' ? rtrim(rtrim(number_format((float) $r->amount, 2), '0'), '.').'% of gross pay' : number_format((float) $r->amount, 2);
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or description" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canBulk)
            <x-slot name="bulk">
                <x-table.bulk-button action="activate">Make active</x-table.bulk-button>
                <x-table.bulk-button action="deactivate">Make inactive</x-table.bulk-button>
                <x-table.bulk-button action="delete" danger confirm="Delete the ticked deductions? Salary structures that already use them keep their own copy.">Delete</x-table.bulk-button>
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($rows->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No deductions match these filters" />
                @else
                    <x-table.empty title="No deductions yet" text="Pension, NHF, staff loans: set each one up once and add it to salary structures.">
                        @can('create payroll')<a href="{{ route('deductions.create') }}" class="btn-new">New deduction</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Deductions" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every deduction on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th field="amount" :sort="[$sortField, $sortDirection]" num>Amount</x-table.th>
                    <x-table.th>Taxed</x-table.th>
                    <x-table.th>Description</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($rows as $row)
                    @php $ticked = in_array((string) $row->id, $selectedItems, true); @endphp
                    <tr wire:key="de-{{ $row->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$row->id" :label="$row->name" />@endif
                        <td><a href="{{ route('deductions.show', $row) }}" class="tbl-link">{{ $row->name }}</a></td>
                        <td class="num">{{ $amount($row) }}</td>
                        <td class="tbl-muted">{{ $row->is_taxable ? 'Yes' : 'No' }}</td>
                        <td class="max-w-[22rem] truncate {{ $row->description ? 'tbl-muted' : 'tbl-zero' }}">{{ $row->description ?: '—' }}</td>
                        <td><x-status-badge :status="$row->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$row->name">
                                <x-table.menu-item :href="route('deductions.show', $row)">View</x-table.menu-item>
                                @can('create payroll')
                                    <x-table.menu-item :href="route('deductions.edit', $row)">Edit</x-table.menu-item>
                                    <x-table.menu-item wire="toggleActive({{ $row->id }})">{{ $row->is_active ? 'Make inactive' : 'Make active' }}</x-table.menu-item>
                                    <x-table.menu-item wire="deleteOne({{ $row->id }})" :confirm="'Delete '.$row->name.'?'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Deductions">
                @foreach ($rows as $row)
                    <li wire:key="de-card-{{ $row->id }}">
                        <x-table.card :href="route('deductions.show', $row)" :title="$row->name" :amount="$amount($row)" :meta="$row->is_taxable ? 'Taxed' : 'Not taxed'">
                            @if (! $row->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$rows" />
</div>
