{{-- Budgets list (tables plan T4). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $canBulk = $user->canAny(['edit budgets', 'delete budgets']);
    $ids = $budgets->pluck('id')->map(fn ($id) => (string) $id)->all();
    $label = ['active' => 'In use'];
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or description" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.pick model="year" label="Year" :options="$years" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit budgets')
                    <x-table.bulk-button action="activate" confirm="Put the ticked draft budgets in use? Only drafts with lines change.">Put in use</x-table.bulk-button>
                    <x-table.bulk-button action="lock" confirm="Lock the ticked budgets? A locked budget can't be changed.">Lock</x-table.bulk-button>
                @endcan
                @can('delete budgets')<x-table.bulk-button action="delete" danger confirm="Delete the ticked budgets? Locked budgets are kept.">Delete</x-table.bulk-button>@endcan
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($budgets->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No budgets match these filters" />
                @else
                    <x-table.empty title="No budgets yet" text="Plan what you expect to earn and spend each month, then compare it with what really happened.">
                        @can('create budgets')<a href="{{ route('budgets.create') }}" class="btn-new">New budget</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Budgets" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every budget on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th field="fiscal_year" :sort="[$sortField, $sortDirection]">Year</x-table.th>
                    <x-table.th num>Lines</x-table.th>
                    <x-table.th field="lines_sum_annual_total" :sort="[$sortField, $sortDirection]" num>Year's total</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($budgets as $budget)
                    @php $ticked = in_array((string) $budget->id, $selectedItems, true); @endphp
                    <tr wire:key="bu-{{ $budget->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$budget->id" :label="$budget->name" />@endif
                        <td class="max-w-[22rem]">
                            <a href="{{ route('budgets.show', $budget) }}" class="tbl-link block truncate">{{ $budget->name }}</a>
                            @if ($budget->description)<div class="truncate text-xs tbl-muted">{{ $budget->description }}</div>@endif
                        </td>
                        <td class="tabular-nums">{{ $budget->fiscal_year }}</td>
                        <td class="num {{ $budget->lines_count ? '' : 'tbl-zero' }}">{{ $budget->lines_count ?: '—' }}</td>
                        <td class="num {{ $budget->lines_sum_annual_total ? '' : 'tbl-zero' }}">{{ $budget->lines_sum_annual_total ? $money($budget->lines_sum_annual_total) : '—' }}</td>
                        <td><x-status-badge :status="$budget->status" :label="$label[$budget->status] ?? null" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$budget->name">
                                <x-table.menu-item :href="route('budgets.show', $budget)">View</x-table.menu-item>
                                <x-table.menu-item :href="route('budgets.vs-actual', $budget)">Budget against actual</x-table.menu-item>
                                @if (! $budget->isLocked())
                                    @can('edit budgets')<x-table.menu-item :href="route('budgets.edit', $budget)">Edit</x-table.menu-item>@endcan
                                    @can('delete budgets')
                                        <x-table.menu-item wire="deleteOne({{ $budget->id }})" :confirm="'Delete '.$budget->name.'?'" danger>Delete</x-table.menu-item>
                                    @endcan
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Budgets">
                @foreach ($budgets as $budget)
                    <li wire:key="bu-card-{{ $budget->id }}">
                        <x-table.card :href="route('budgets.show', $budget)" :title="$budget->name"
                            :amount="$budget->lines_sum_annual_total ? \App\Support\Money::format($budget->lines_sum_annual_total) : null" :meta="$budget->fiscal_year.' · '.$budget->lines_count.' lines'">
                            <x-slot name="badge"><x-status-badge :status="$budget->status" :label="$label[$budget->status] ?? null" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$budgets" />
</div>
