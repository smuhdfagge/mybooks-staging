<div class="relative">
    <x-table-loading />
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="st-search" class="form-label">Search</label>
            <input type="text" id="st-search" wire:model.live.debounce.300ms="search" placeholder="Number, reference, item..." class="form-control text-sm">
        </div>
        <div>
            <label for="st-status" class="form-label">Status</label>
            <select id="st-status" wire:model.live="status" class="form-control text-sm">
                <option value="">All statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="st-warehouse" class="form-label">Warehouse</label>
            <select id="st-warehouse" wire:model.live="warehouse" class="form-control text-sm">
                <option value="">All warehouses</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-300">
                <tr>
                    <x-sort-header field="transfer_number" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Number</x-sort-header>
                    <x-sort-header field="transfer_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Date</x-sort-header>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">From → to</th>
                    <th scope="col" class="px-4 py-3 text-right uppercase tracking-wider font-medium hidden sm:table-cell">Items</th>
                    <th scope="col" class="px-4 py-3 text-right uppercase tracking-wider font-medium hidden md:table-cell">Cost</th>
                    <x-sort-header field="status" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Status</x-sort-header>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                @forelse($transfers as $transfer)
                    <tr wire:key="st-{{ $transfer->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('stock-transfers.show', $transfer) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $transfer->transfer_number }}</a>
                            @if($transfer->reference)<span class="block text-xs text-gray-500 dark:text-gray-400">{{ $transfer->reference }}</span>@endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $transfer->transfer_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $transfer->fromWarehouse?->name }} <span class="text-gray-400" aria-hidden="true">→</span><span class="sr-only">to</span> {{ $transfer->toWarehouse?->name }}</td>
                        <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100 hidden sm:table-cell">{{ $transfer->items_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900 dark:text-gray-100 hidden md:table-cell">@if($transfer->status === 'draft')—@else @money($transfer->items_sum_shipped_cost ?? 0)@endif</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$transfer->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No stock transfers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <label for="st-per-page" class="sr-only">Rows per page</label>
            <select id="st-per-page" wire:model.live="perPage" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                @foreach([10, 15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }} per page</option>@endforeach
            </select>
        </div>
        @if($transfers->hasPages())<div>{{ $transfers->links() }}</div>@endif
    </div>
</div>
