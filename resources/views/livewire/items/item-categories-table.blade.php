{{-- Item categories list (tables plan T4). --}}
@php
    $user = auth()->user();
    $canBulk = $user->canAny(['edit items', 'delete items']);
    $ids = $categories->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search name or description" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit items')
                    <x-table.bulk-button action="activate">Make active</x-table.bulk-button>
                    <x-table.bulk-button action="deactivate">Make inactive</x-table.bulk-button>
                @endcan
                @can('delete items')<x-table.bulk-button action="delete" danger confirm="Delete the ticked categories? Categories with items or sub-categories are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$categories" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($categories->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No categories match these filters" />
                @else
                    <x-table.empty title="No categories yet" text="Group your items, for example Drinks or Spare parts.">
                        @can('create items')<a href="{{ route('item-categories.create') }}" class="btn-new">New category</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Item categories" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every category on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th>Inside</x-table.th>
                    <x-table.th>Description</x-table.th>
                    <x-table.th field="items_count" :sort="[$sortField, $sortDirection]" num>Items</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($categories as $category)
                    @php $ticked = in_array((string) $category->id, $selectedItems, true); @endphp
                    <tr wire:key="ic-{{ $category->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$category->id" :label="$category->name" />@endif
                        <td><a href="{{ route('item-categories.show', $category) }}" class="tbl-link">{{ $category->name }}</a></td>
                        <td class="{{ $category->parent ? 'tbl-muted' : 'tbl-zero' }}">{{ $category->parent?->name ?? '—' }}</td>
                        <td class="max-w-[22rem] truncate {{ $category->description ? 'tbl-muted' : 'tbl-zero' }}">{{ $category->description ?: '—' }}</td>
                        <td class="num {{ $category->items_count ? '' : 'tbl-zero' }}">{{ $category->items_count ? number_format($category->items_count) : '—' }}</td>
                        <td><x-status-badge :status="$category->is_active ? 'active' : 'inactive'" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$category->name">
                                <x-table.menu-item :href="route('item-categories.show', $category)">View</x-table.menu-item>
                                @can('edit items')
                                    <x-table.menu-item :href="route('item-categories.edit', $category)">Edit</x-table.menu-item>
                                    <x-table.menu-item wire="toggleActive({{ $category->id }})">{{ $category->is_active ? 'Make inactive' : 'Make active' }}</x-table.menu-item>
                                @endcan
                                @if ($user->can('delete items') && ! $category->items_count && ! $category->children_count)
                                    <x-table.menu-item wire="deleteOne({{ $category->id }})" :confirm="'Delete '.$category->name.'?'" danger>Delete</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Item categories">
                @foreach ($categories as $category)
                    <li wire:key="ic-card-{{ $category->id }}">
                        <x-table.card :href="route('item-categories.show', $category)" :title="$category->name"
                            :meta="number_format($category->items_count).' '.($category->items_count == 1 ? 'item' : 'items').($category->parent ? ' · in '.$category->parent->name : '')">
                            @if (! $category->is_active)
                                <x-slot name="badge"><x-status-badge status="inactive" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$categories" />
</div>
