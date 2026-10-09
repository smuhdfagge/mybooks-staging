<div class="relative">
    <x-table-loading />
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="as-search" class="form-label">Search</label>
            <input type="text" id="as-search" wire:model.live.debounce.300ms="search" placeholder="Number, description, reference..." class="form-control text-sm">
        </div>
        <div>
            <label for="as-type" class="form-label">Type</label>
            <select id="as-type" wire:model.live="type" class="form-control text-sm">
                <option value="">All types</option>
                @foreach(\App\Models\AccrualSchedule::TYPES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="as-status" class="form-label">Status</label>
            <select id="as-status" wire:model.live="status" class="form-control text-sm">
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
                    <x-sort-header field="schedule_number" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Number</x-sort-header>
                    <x-sort-header field="description" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Description</x-sort-header>
                    <x-sort-header field="start_date" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Months</x-sort-header>
                    <x-sort-header field="total_amount" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-right">Total</x-sort-header>
                    <x-sort-header field="released_amount" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-right">Released</x-sort-header>
                    <th scope="col" class="px-4 py-3 text-right uppercase tracking-wider font-medium">Left</th>
                    <x-sort-header field="status" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3 text-left">Status</x-sort-header>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                @forelse($schedules as $schedule)
                    <tr wire:key="as-{{ $schedule->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 whitespace-nowrap"><a href="{{ route('accrual-schedules.show', $schedule) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $schedule->schedule_number }}</a></td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                            {{ $schedule->description }}
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $schedule->typeLabel() }} · {{ $schedule->plAccount?->name }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">
                            {{ $schedule->start_date->format('M Y') }} – {{ $schedule->dueDate($schedule->months)->format('M Y') }}
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $schedule->months }} {{ \Illuminate\Support\Str::plural('month', $schedule->months) }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-900 dark:text-gray-100">@money($schedule->total_amount)</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-900 dark:text-gray-100">@money($schedule->released_amount)</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-900 dark:text-gray-100">@money($schedule->remaining())</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$schedule->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No schedules yet. Use "New schedule" to spread a payment made or received in advance over the months it covers.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <label for="as-per-page" class="sr-only">Rows per page</label>
            <select id="as-per-page" wire:model.live="perPage" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                @foreach([10, 15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }} per page</option>@endforeach
            </select>
        </div>
        @if($schedules->hasPages())<div>{{ $schedules->links() }}</div>@endif
    </div>
</div>
