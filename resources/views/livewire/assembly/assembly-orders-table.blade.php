<div class="relative">
    <x-table-loading />
    <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <label for="asm-search" class="form-label">Search</label>
            <input type="text" id="asm-search" wire:model.live.debounce.300ms="search" placeholder="Number, item, bill..." class="form-control text-sm">
        </div>
        <div>
            <label for="asm-status" class="form-label">Status</label>
            <select id="asm-status" wire:model.live="status" class="form-control text-sm">
                <option value="">All statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="asm-kind" class="form-label">Type</label>
            <select id="asm-kind" wire:model.live="kind" class="form-control text-sm">
                <option value="">Builds and break-downs</option>
                <option value="build">Builds</option>
                <option value="breakdown">Break-downs</option>
            </select>
        </div>
        @if($warehouses->isNotEmpty())
            <div>
                <label for="asm-warehouse" class="form-label">Warehouse</label>
                <select id="asm-warehouse" wire:model.live="warehouse" class="form-control text-sm">
                    <option value="">All warehouses</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-300">
                <tr>
                    <x-sort-header field="order_number" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Number</x-sort-header>
                    <x-sort-header field="assembly_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Date</x-sort-header>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">Item</th>
                    <th scope="col" class="px-4 py-3 text-right uppercase tracking-wider font-medium">Quantity</th>
                    <x-sort-header field="total_cost" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-right hidden md:table-cell">Cost</x-sort-header>
                    <x-sort-header field="status" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Status</x-sort-header>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                @forelse($orders as $order)
                    @php $qty = $order->isCompleted() ? (float) $order->quantity_made : $order->plannedQuantity(); @endphp
                    <tr wire:key="asm-{{ $order->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('assembly-orders.show', $order) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $order->order_number }}</a>
                            @if($order->isBreakdown())<span class="block text-xs text-gray-500 dark:text-gray-400">Break-down</span>@endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $order->assembly_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $order->billOfMaterial?->item?->name }}
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $order->billOfMaterial?->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format($qty, 4), '0'), '.') }} {{ $order->billOfMaterial?->item?->unit }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900 dark:text-gray-100 hidden md:table-cell">@if($order->isCompleted()) @money($order->total_cost) @else — @endif</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$order->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No assembly orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <label for="asm-per-page" class="sr-only">Rows per page</label>
            <select id="asm-per-page" wire:model.live="perPage" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                @foreach([10, 15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }} per page</option>@endforeach
            </select>
        </div>
        @if($orders->hasPages())<div>{{ $orders->links() }}</div>@endif
    </div>
</div>
