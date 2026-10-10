{{-- Tax rates list (tables plan T4). --}}
@php
    $user = auth()->user();
    $canBulk = $user->canAny(['edit tax-rates', 'delete tax-rates']);
    $ids = $taxRates->pluck('id')->map(fn ($id) => (string) $id)->all();
    $rate = fn ($r) => rtrim(rtrim(number_format((float) $r, 4), '0'), '.').'%';
    $on = ['sales' => 'Sales', 'purchases' => 'Purchases', 'both' => 'Sales and purchases'];
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or code" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit tax-rates')
                    <x-table.bulk-button action="activate">Make active</x-table.bulk-button>
                    <x-table.bulk-button action="deactivate" confirm="Make the ticked rates inactive? They drop out of pick lists; past documents keep them.">Make inactive</x-table.bulk-button>
                @endcan
                @can('delete tax-rates')<x-table.bulk-button action="delete" danger confirm="Delete the ticked rates? Rates used in tax groups or on items are skipped.">Delete</x-table.bulk-button>@endcan
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($taxRates->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No tax rates match these filters" />
                @else
                    <x-table.empty title="No tax rates yet" text="Add the rates you charge on sales and pay on purchases, such as VAT at 7.5%.">
                        @can('create tax-rates')<a href="{{ route('tax-rates.create') }}" class="btn-new">New tax rate</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Tax rates" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every rate on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th>Code</x-table.th>
                    <x-table.th field="rate" :sort="[$sortField, $sortDirection]" num>Rate</x-table.th>
                    <x-table.th>Price</x-table.th>
                    <x-table.th>Used on</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($taxRates as $taxRate)
                    @php $ticked = in_array((string) $taxRate->id, $selectedItems, true); @endphp
                    <tr wire:key="tr-{{ $taxRate->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$taxRate->id" :label="$taxRate->name" />@endif
                        <td>
                            <a href="{{ route('tax-rates.show', $taxRate) }}" class="tbl-link">{{ $taxRate->name }}</a>
                            @if ($taxRate->is_default)<div class="text-xs tbl-muted">Default</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $taxRate->code ?: '—' }}</td>
                        <td class="num">{{ $rate($taxRate->rate) }}</td>
                        <td class="tbl-muted">{{ $taxRate->type === 'inclusive' ? 'Includes tax' : 'Tax added on top' }}</td>
                        <td class="tbl-muted">{{ $on[$taxRate->applies_to] ?? ucfirst((string) $taxRate->applies_to) }}</td>
                        <td><x-status-badge :status="$taxRate->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$taxRate->name">
                                <x-table.menu-item :href="route('tax-rates.show', $taxRate)">View</x-table.menu-item>
                                @can('edit tax-rates')
                                    <x-table.menu-item :href="route('tax-rates.edit', $taxRate)">Edit</x-table.menu-item>
                                    @if (! $taxRate->is_default && $taxRate->is_active)<x-table.menu-item wire="toggleDefault({{ $taxRate->id }})">Make it the default</x-table.menu-item>@endif
                                    <x-table.menu-item wire="toggleActive({{ $taxRate->id }})">{{ $taxRate->is_active ? 'Make inactive' : 'Make active' }}</x-table.menu-item>
                                @endcan
                                @can('delete tax-rates')
                                    <x-table.menu-item wire="deleteOne({{ $taxRate->id }})" :confirm="'Delete '.$taxRate->name.'?'" danger>Delete</x-table.menu-item>
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Tax rates">
                @foreach ($taxRates as $taxRate)
                    <li wire:key="tr-card-{{ $taxRate->id }}">
                        <x-table.card :href="route('tax-rates.show', $taxRate)" :title="$taxRate->name" :amount="$rate($taxRate->rate)"
                            :meta="($on[$taxRate->applies_to] ?? '').($taxRate->is_default ? ' · Default' : '')">
                            @if (! $taxRate->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$taxRates" />
</div>
