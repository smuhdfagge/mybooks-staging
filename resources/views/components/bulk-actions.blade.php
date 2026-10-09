@props(['actions', 'selectedCount' => 0])

<div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bulk Actions</label>
    <div class="flex space-x-2">
        <select aria-label="Bulk action" wire:model="bulkAction" class="flex-1 min-w-0 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
            <option value="">Select Action</option>
            @foreach($actions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <button type="button" wire:click="applyBulkAction" wire:confirm="Are you sure you want to apply this action to the selected items?"
            class="flex-shrink-0 px-3 py-2 bg-brand-600 text-white text-sm font-medium rounded-md hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50"
            {{ $selectedCount === 0 ? 'disabled' : '' }}>
            Apply
        </button>
    </div>
    @if($selectedCount > 0)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $selectedCount }} selected</p>
    @endif
</div>
