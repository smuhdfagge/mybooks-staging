{{-- Fixed assets list (tables plan T4), with the bulk write-off dialog (F2). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $date = fn ($d) => $d ? $d->format('j M Y') : '—';
    $labels = \App\Livewire\FixedAssets\FixedAssetsTable::LABELS;
    $tone = ['active' => 'active', 'under_maintenance' => 'pending', 'idle' => 'inactive', 'fully_depreciated' => 'completed', 'disposed' => 'cancelled', 'sold' => 'closed'];
    $gone = fn ($a) => in_array($a->status, ['disposed', 'sold'], true);
    $canBulk = $user->canAny(['edit fixed-assets', 'delete fixed-assets']);
    $ids = $assets->pluck('id')->map(fn ($id) => (string) $id)->all();
@endphp
<div class="relative space-y-3">
    <x-flash-messages :successMessage="$successMessage" :errorMessage="$errorMessage" />

    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search number, name, serial number or location" :selected="count($selectedItems)" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.pick model="category" label="Category" :options="$categories" />
        </x-slot>
        @if ($canBulk)
            <x-slot name="bulk">
                @can('edit fixed-assets')
                    <x-table.bulk-button action="activate">Put back in use</x-table.bulk-button>
                    <x-table.bulk-button action="dispose">Write off</x-table.bulk-button>
                @endcan
                @can('delete fixed-assets')<x-table.bulk-button action="delete" danger confirm="Delete the ticked assets? Assets with depreciation posted are skipped.">Delete</x-table.bulk-button>@endcan
                <x-table.tick-all-matching :rows="$assets" :selected="$selectedItems" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($assets->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No assets match these filters" />
                @else
                    <x-table.empty title="No fixed assets yet" text="Record vehicles, generators, computers and other things you keep for years. MyBooks works out the depreciation.">
                        @can('create fixed-assets')<a href="{{ route('fixed-assets.create') }}" class="btn-new">New asset</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Fixed assets" class="hidden md:block">
                <x-slot name="head">
                    @if ($canBulk)<x-table.check-all :ids="$ids" :selected="$selectedItems" label="Tick every asset on this page" />@endif
                    <x-table.th field="asset_number" :sort="[$sortField, $sortDirection]">Number</x-table.th>
                    <x-table.th field="name" :sort="[$sortField, $sortDirection]">Asset</x-table.th>
                    <x-table.th>Category</x-table.th>
                    <x-table.th field="purchase_date" :sort="[$sortField, $sortDirection]">Bought</x-table.th>
                    <x-table.th field="purchase_cost" :sort="[$sortField, $sortDirection]" num>Cost</x-table.th>
                    <x-table.th num>Depreciation</x-table.th>
                    <x-table.th field="book_value" :sort="[$sortField, $sortDirection]" num>Book value</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($assets as $asset)
                    @php $ticked = in_array((string) $asset->id, $selectedItems, true); @endphp
                    <tr wire:key="fa-{{ $asset->id }}" @if ($ticked) data-picked @endif>
                        @if ($canBulk)<x-table.check :id="$asset->id" :label="$asset->name" />@endif
                        <td><a href="{{ route('fixed-assets.show', $asset) }}" class="tbl-link">{{ $asset->asset_number }}</a></td>
                        <td class="max-w-[18rem]">
                            <span class="block truncate">{{ $asset->name }}</span>
                            @if ($asset->location)<span class="block truncate text-xs tbl-muted">{{ $asset->location }}</span>@endif
                        </td>
                        <td class="max-w-[12rem] truncate tbl-muted">{{ $asset->category?->name ?? '—' }}</td>
                        <td class="tbl-muted">{{ $date($asset->purchase_date) }}</td>
                        <td class="num">{{ $money($asset->purchase_cost) }}</td>
                        <td class="num {{ (float) $asset->accumulated_depreciation > 0 ? '' : 'tbl-zero' }}">{{ (float) $asset->accumulated_depreciation > 0 ? $money($asset->accumulated_depreciation) : '—' }}</td>
                        <td class="num {{ $gone($asset) ? 'tbl-zero' : '' }}">{{ $gone($asset) ? '—' : $money($asset->book_value) }}</td>
                        <td><x-status-badge :status="$tone[$asset->status] ?? 'draft'" :label="$labels[$asset->status] ?? ucfirst($asset->status)" /></td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$asset->name">
                                <x-table.menu-item :href="route('fixed-assets.show', $asset)">{{ $gone($asset) ? 'View' : 'View, depreciate or dispose of' }}</x-table.menu-item>
                                <x-table.menu-item :href="route('fixed-assets.schedule', $asset)">Depreciation schedule</x-table.menu-item>
                                @if (! $gone($asset))
                                    @can('edit fixed-assets')<x-table.menu-item :href="route('fixed-assets.edit', $asset)">Edit</x-table.menu-item>@endcan
                                @endif
                                @if ((float) $asset->accumulated_depreciation == 0 && $user->can('delete fixed-assets'))
                                    <x-table.menu-item wire="deleteOne({{ $asset->id }})" :confirm="'Delete '.$asset->name.'?'" danger>Delete</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        @if ($canBulk)<td></td>@endif
                        <td colspan="4">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'asset' : 'assets' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->cost) }}</td>
                        <td class="num">{{ $money($totals->depreciation) }}</td>
                        <td class="num">{{ $money($totals->book_value) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Fixed assets">
                @foreach ($assets as $asset)
                    <li wire:key="fa-card-{{ $asset->id }}">
                        <x-table.card :href="route('fixed-assets.show', $asset)" :title="$asset->name" :amount="$gone($asset) ? null : \App\Support\Money::format($asset->book_value)"
                            :meta="$asset->asset_number.' · '.($asset->category?->name ?? '').' · cost '.\App\Support\Money::format($asset->purchase_cost)">
                            @if ($asset->status !== 'active')
                                <x-slot name="badge"><x-status-badge :status="$tone[$asset->status] ?? 'draft'" :label="$labels[$asset->status] ?? ucfirst($asset->status)" /></x-slot>
                            @endif
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Book value {{ \App\Support\Money::format($totals->book_value) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$assets" />

    {{-- Write off the ticked assets (F2) --}}
    @if ($showDisposeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="dispose-modal-title"
            x-data x-trap.inert.noscroll="true" @keydown.escape.window="$wire.closeDisposeModal()">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeDisposeModal" aria-hidden="true"></div>
            <div class="relative w-full max-w-lg space-y-4 rounded-lg bg-white p-5 shadow-xl dark:bg-gray-800">
                <h3 id="dispose-modal-title" class="text-base font-semibold text-gray-900 dark:text-white">Write off {{ count($selectedItems) }} asset(s)</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Each asset leaves the books with no money received: its cost and depreciation are removed and what is left of its value is posted as a loss.
                    <strong class="text-gray-900 dark:text-white">If you sold an asset, dispose of it on its own page instead</strong>, so you can enter what you got for it.
                </p>
                <div>
                    <label for="disposalDate" class="form-label">Date</label>
                    <input type="date" id="disposalDate" wire:model="disposalDate" max="{{ now()->toDateString() }}" class="form-control">
                    @error('disposalDate')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="disposalMethod" class="form-label">What happened to them</label>
                    <select id="disposalMethod" wire:model="disposalMethod" class="form-control">
                        <option value="scrapped">Scrapped</option>
                        <option value="donated">Donated</option>
                        <option value="lost">Lost or stolen</option>
                        <option value="other">Other</option>
                    </select>
                    @error('disposalMethod')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="disposalReason" class="form-label">Reason (optional)</label>
                    <textarea id="disposalReason" wire:model="disposalReason" rows="2" class="form-control" placeholder="For example: broken beyond repair"></textarea>
                    @error('disposalReason')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-400">Assets already disposed of or sold are skipped.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeDisposeModal" class="tbl-chip h-9">Keep them</button>
                    <button type="button" wire:click="disposeSelected" wire:loading.attr="disabled" class="inline-flex h-9 items-center rounded-md bg-red-700 px-3 text-sm font-semibold text-white hover:bg-red-800">Write off</button>
                </div>
            </div>
        </div>
    @endif
</div>
