{{-- Bills of materials list (tables plan T4). --}}
@php
    $user = auth()->user();
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or item" :filtered="$filtered" />

    <div class="relative">
        <x-table.veil />
        @if ($boms->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No bills of materials match these filters" />
                @else
                    <x-table.empty title="No bills of materials yet" text="List the parts and costs that go into something you make, then build it with an assembly order.">
                        @can('create items')<a href="{{ route('bill-of-materials.create') }}" class="btn-new">New bill of materials</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Bills of materials" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th>Makes</x-table.th>
                    <x-table.th num>Parts</x-table.th>
                    <x-table.th field="assembly_orders_count" :sort="[$sortField, $sortDirection]" num>Times built</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($boms as $bom)
                    <tr wire:key="bom-{{ $bom->id }}">
                        <td><a href="{{ route('bill-of-materials.show', $bom) }}" class="tbl-link">{{ $bom->label() }}</a></td>
                        <td class="max-w-[20rem] truncate">{{ $qty($bom->output_quantity) }} × {{ $bom->item?->name ?? '—' }}</td>
                        <td class="num">{{ number_format($bom->components_count) }}</td>
                        <td class="num {{ $bom->assembly_orders_count ? '' : 'tbl-zero' }}">{{ $bom->assembly_orders_count ? number_format($bom->assembly_orders_count) : '—' }}</td>
                        <td><x-status-badge :status="$bom->is_active ? 'active' : 'inactive'" :label="$bom->is_active ? 'In use' : 'Not in use'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$bom->label()">
                                <x-table.menu-item :href="route('bill-of-materials.show', $bom)">View</x-table.menu-item>
                                @can('edit items')<x-table.menu-item :href="route('bill-of-materials.edit', $bom)">Edit</x-table.menu-item>@endcan
                                @if ($bom->is_active && $user->can('adjust inventory'))
                                    <x-table.menu-item :href="route('assembly-orders.create', ['bill' => $bom->id])">Build it</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Bills of materials">
                @foreach ($boms as $bom)
                    <li wire:key="bom-card-{{ $bom->id }}">
                        <x-table.card :href="route('bill-of-materials.show', $bom)" :title="$bom->label()" :meta="'Makes '.$qty($bom->output_quantity).' × '.($bom->item?->name ?? '—').' · '.$bom->components_count.' parts'">
                            @if (! $bom->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" label="Not in use" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$boms" />
</div>
