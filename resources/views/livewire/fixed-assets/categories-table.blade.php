{{-- Fixed asset categories (tables plan T4). --}}
@php
    $user = auth()->user();
    $canBulk = $user->can('delete fixed-assets');
    $ids = $categories->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.toolbar placeholder="Search name, code or description" :selected="count($selectedItems)" :filtered="$filtered">
        @if ($canBulk)
            <x-slot name="bulk">
                <x-table.bulk-button action="delete" danger confirm="Delete the ticked categories? Categories with assets are skipped.">Delete</x-table.bulk-button>
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($categories->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered)
                    <x-table.empty filtered title="No categories match this search" />
                @else
                    <x-table.empty title="No asset categories yet" text="A category sets how long its assets last, how they are depreciated and which accounts they post to.">
                        @can('create fixed-assets')<a href="{{ route('fixed-asset-categories.create') }}" class="btn-new">New category</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Asset categories" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every category on this page" />@endif
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Name</x-table.th>
                    <x-table.th>Code</x-table.th>
                    <x-table.th field="default_useful_life" :sort="[$sortField, $sortDirection]" num>Lasts (years)</x-table.th>
                    <x-table.th>Depreciation</x-table.th>
                    <x-table.th field="assets_count" :sort="[$sortField, $sortDirection]" num>Assets</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($categories as $category)
                    @php $ticked = in_array((string) $category->id, $selectedItems, true); @endphp
                    <tr wire:key="fac-{{ $category->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$category->id" :label="$category->name" />@endif
                        <td class="max-w-[20rem]">
                            <a href="{{ route('fixed-asset-categories.show', $category) }}" class="tbl-link block truncate">{{ $category->name }}</a>
                            @if ($category->description)<div class="truncate text-xs tbl-muted">{{ $category->description }}</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $category->code ?: '—' }}</td>
                        <td class="num">{{ (float) $category->default_useful_life ? rtrim(rtrim(number_format((float) $category->default_useful_life, 2), '0'), '.') : '—' }}</td>
                        <td class="tbl-muted">{{ $methods[$category->default_depreciation_method] ?? '—' }}</td>
                        <td class="num {{ $category->assets_count ? '' : 'tbl-zero' }}">{{ $category->assets_count ?: '—' }}</td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$category->name">
                                <x-table.menu-item :href="route('fixed-asset-categories.show', $category)">View</x-table.menu-item>
                                @can('edit fixed-assets')<x-table.menu-item :href="route('fixed-asset-categories.edit', $category)">Edit</x-table.menu-item>@endcan
                                <x-table.menu-item :href="route('fixed-assets.index', ['category' => $category->id])">Its assets</x-table.menu-item>
                                @if (! $category->assets_count && $user->can('delete fixed-assets'))
                                    <x-table.menu-item wire="deleteOne({{ $category->id }})" :confirm="'Delete '.$category->name.'?'" danger>Delete</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Asset categories">
                @foreach ($categories as $category)
                    <li wire:key="fac-card-{{ $category->id }}">
                        <x-table.card :href="route('fixed-asset-categories.show', $category)" :title="$category->name"
                            :meta="((float) $category->default_useful_life ? rtrim(rtrim(number_format((float) $category->default_useful_life, 2), '0'), '.').' years · ' : '').$category->assets_count.' assets'" />
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$categories" />
</div>
