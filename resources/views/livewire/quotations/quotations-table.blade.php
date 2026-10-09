<div class="relative">
    <x-table-loading />
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="q-search" class="form-label">Search</label>
            <input type="text" id="q-search" wire:model.live.debounce.300ms="search" placeholder="Number, reference, customer..." class="form-control text-sm">
        </div>
        <div>
            <label for="q-status" class="form-label">Status</label>
            <select id="q-status" wire:model.live="status" class="form-control text-sm">
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
                    <x-sort-header field="quotation_number" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Number</x-sort-header>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">Customer</th>
                    <x-sort-header field="quotation_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Date</x-sort-header>
                    <x-sort-header field="expiry_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Valid until</x-sort-header>
                    <x-sort-header field="total" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-right">Total</x-sort-header>
                    <x-sort-header field="status" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Status</x-sort-header>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                @forelse($quotations as $quotation)
                    <tr wire:key="quotation-{{ $quotation->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('quotations.show', $quotation) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $quotation->quotation_number }}</a>
                            @if($quotation->reference)<p class="text-xs text-gray-500 dark:text-gray-400">{{ $quotation->reference }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $quotation->customer?->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $quotation->quotation_date->format('M d, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $quotation->expiry_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right font-medium text-gray-900 dark:text-gray-100">@money($quotation->total)</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$quotation->status" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                            No quotations yet.
                            @can('create invoices')
                                <a href="{{ route('quotations.create') }}" class="text-brand-600 dark:text-brand-300 hover:underline">Create your first quotation</a>.
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <label for="q-per-page" class="sr-only">Rows per page</label>
            <select id="q-per-page" wire:model.live="perPage" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                @foreach([10, 15, 25, 50, 100] as $size)
                    <option value="{{ $size }}">{{ $size }} per page</option>
                @endforeach
            </select>
        </div>
        @if($quotations->hasPages())<div>{{ $quotations->links() }}</div>@endif
    </div>
</div>
