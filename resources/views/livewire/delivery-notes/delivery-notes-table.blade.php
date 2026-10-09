<div class="relative">
    <x-table-loading />
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="dn-search" class="form-label">Search</label>
            <input type="text" id="dn-search" wire:model.live.debounce.300ms="search" placeholder="Number, order, customer..." class="form-control text-sm">
        </div>
        <div>
            <label for="dn-status" class="form-label">Status</label>
            <select id="dn-status" wire:model.live="status" class="form-control text-sm">
                <option value="">All statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-300">
                <tr>
                    <x-sort-header field="delivery_number" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Number</x-sort-header>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">Customer</th>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">Sales order</th>
                    <x-sort-header field="delivery_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Date</x-sort-header>
                    <x-sort-header field="status" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Status</x-sort-header>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                @forelse($notes as $note)
                    <tr wire:key="dn-{{ $note->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 whitespace-nowrap"><a href="{{ route('delivery-notes.show', $note) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $note->delivery_number }}</a></td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $note->customer?->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $note->salesOrder?->order_number ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $note->delivery_date->format('M d, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$note->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No delivery notes yet. Make one from a confirmed sales order.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <label for="dn-per-page" class="sr-only">Rows per page</label>
            <select id="dn-per-page" wire:model.live="perPage" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                @foreach([10, 15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }} per page</option>@endforeach
            </select>
        </div>
        @if($notes->hasPages())<div>{{ $notes->links() }}</div>@endif
    </div>
</div>
