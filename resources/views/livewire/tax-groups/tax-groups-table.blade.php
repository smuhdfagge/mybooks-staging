{{-- Tax groups list (tables plan T4). --}}
@php
    $user = auth()->user();
    $canBulk = $user->canAny(['edit tax-rates', 'delete tax-rates']);
    $ids = $taxGroups->pluck('id')->map(fn ($id) => (string) $id)->all();
    $rate = fn ($r) => rtrim(rtrim(number_format((float) $r, 4), '0'), '.').'%';
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or code" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit tax-rates')
                    <x-table.bulk-button action="activate">Make active</x-table.bulk-button>
                    <x-table.bulk-button action="deactivate">Make inactive</x-table.bulk-button>
                @endcan
                @can('delete tax-rates')<x-table.bulk-button action="delete" danger confirm="Delete the ticked groups? Groups used on items are skipped.">Delete</x-table.bulk-button>@endcan
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($taxGroups->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No tax groups match these filters" />
                @else
                    <x-table.empty title="No tax groups yet" text="Put rates that are charged together into one group, then pick the group on an item or line.">
                        @can('create tax-rates')<a href="{{ route('tax-groups.create') }}" class="btn-new">New tax group</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Tax groups" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every group on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th field="code" :sort="[$sortField, $sortDirection]">Code</x-table.th>
                    <x-table.th>Rates in it</x-table.th>
                    <x-table.th num>Together</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($taxGroups as $taxGroup)
                    @php $ticked = in_array((string) $taxGroup->id, $selectedItems, true); @endphp
                    <tr wire:key="tg-{{ $taxGroup->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$taxGroup->id" :label="$taxGroup->name" />@endif
                        <td>
                            <a href="{{ route('tax-groups.show', $taxGroup) }}" class="tbl-link">{{ $taxGroup->name }}</a>
                            @if ($taxGroup->is_default)<div class="text-xs tbl-muted">Default</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $taxGroup->code ?: '—' }}</td>
                        <td class="max-w-[22rem] truncate tbl-muted">{{ $taxGroup->taxRates->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="num">{{ $rate($taxGroup->combined_rate) }}</td>
                        <td><x-status-badge :status="$taxGroup->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$taxGroup->name">
                                <x-table.menu-item :href="route('tax-groups.show', $taxGroup)">View</x-table.menu-item>
                                @can('edit tax-rates')
                                    <x-table.menu-item :href="route('tax-groups.edit', $taxGroup)">Edit</x-table.menu-item>
                                    <x-table.menu-item wire="toggleActive({{ $taxGroup->id }})">{{ $taxGroup->is_active ? 'Make inactive' : 'Make active' }}</x-table.menu-item>
                                @endcan
                                @can('delete tax-rates')
                                    <x-table.menu-item wire="deleteOne({{ $taxGroup->id }})" :confirm="'Delete '.$taxGroup->name.'?'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Tax groups">
                @foreach ($taxGroups as $taxGroup)
                    <li wire:key="tg-card-{{ $taxGroup->id }}">
                        <x-table.card :href="route('tax-groups.show', $taxGroup)" :title="$taxGroup->name" :amount="$rate($taxGroup->combined_rate)" :meta="$taxGroup->taxRates->pluck('name')->implode(', ')">
                            @if (! $taxGroup->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$taxGroups" />
</div>
