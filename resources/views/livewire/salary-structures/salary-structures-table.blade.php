{{-- Salary structures (tables plan T5). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $canBulk = $user->can('create payroll');
    $ids = $structures->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or notes" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canBulk)
            <x-slot name="bulk">
                <x-table.bulk-button action="activate">Put in use</x-table.bulk-button>
                <x-table.bulk-button action="deactivate">Take out of use</x-table.bulk-button>
                <x-table.bulk-button action="delete" danger confirm="Delete the ticked structures? Structures used by employees or payroll runs are skipped.">Delete</x-table.bulk-button>
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($structures->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No structures match these filters" />
                @else
                    <x-table.empty title="No salary structures yet" text="Set basic pay with its allowances and deductions once, then give it to each employee on that grade.">
                        @can('create payroll')<a href="{{ route('salary-structures.create') }}" class="btn-new">New structure</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Salary structures" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every structure on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th field="basic_salary" :sort="[$sortField, $sortDirection]" num>Basic pay</x-table.th>
                    <x-table.th num>Allowances</x-table.th>
                    <x-table.th num>Deductions</x-table.th>
                    <x-table.th num>Take-home</x-table.th>
                    <x-table.th field="employees_count" :sort="[$sortField, $sortDirection]" num>Staff on it</x-table.th>
                    <x-table.th field="effective_from" :sort="[$sortField, $sortDirection]">From</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($structures as $structure)
                    @php $ticked = in_array((string) $structure->id, $selectedItems, true); @endphp
                    <tr wire:key="ss-{{ $structure->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$structure->id" :label="$structure->name" />@endif
                        <td>
                            <a href="{{ route('salary-structures.show', $structure) }}" class="tbl-link">{{ $structure->name }}</a>
                            @if ($structure->version > 1)<div class="text-xs tbl-muted">Version {{ $structure->version }}</div>@endif
                        </td>
                        <td class="num">{{ $money($structure->basic_salary) }}</td>
                        <td class="num {{ $structure->total_allowances > 0 ? '' : 'tbl-zero' }}">{{ $structure->total_allowances > 0 ? $money($structure->total_allowances) : '—' }}</td>
                        <td class="num {{ $structure->total_deductions > 0 ? '' : 'tbl-zero' }}">{{ $structure->total_deductions > 0 ? $money($structure->total_deductions) : '—' }}</td>
                        <td class="num">{{ $money($structure->net_salary) }}</td>
                        <td class="num {{ $structure->employees_count ? '' : 'tbl-zero' }}">{{ $structure->employees_count ?: '—' }}</td>
                        <td class="tbl-muted">{{ $structure->effective_from?->format('j M Y') ?? '—' }}</td>
                        <td><x-status-badge :status="$structure->is_active ? 'active' : 'inactive'" :label="$structure->is_active ? 'In use' : 'Not in use'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$structure->name">
                                <x-table.menu-item :href="route('salary-structures.show', $structure)">View</x-table.menu-item>
                                @can('create payroll')
                                    <x-table.menu-item :href="route('salary-structures.edit', $structure)">Edit</x-table.menu-item>
                                    <x-table.menu-item wire="toggleActive({{ $structure->id }})">{{ $structure->is_active ? 'Take out of use' : 'Put in use' }}</x-table.menu-item>
                                    @if (! $structure->employees_count)
                                        <x-table.menu-item wire="deleteOne({{ $structure->id }})" :confirm="'Delete '.$structure->name.'?'" danger>Delete</x-table.menu-item>
                                    @endif
                                @endcan
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Salary structures">
                @foreach ($structures as $structure)
                    <li wire:key="ss-card-{{ $structure->id }}">
                        <x-table.card :href="route('salary-structures.show', $structure)" :title="$structure->name" :amount="\App\Support\Money::format($structure->net_salary)"
                            :meta="'Basic '.\App\Support\Money::format($structure->basic_salary).' · '.$structure->employees_count.' staff'">
                            @if (! $structure->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" label="Not in use" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$structures" />
</div>
