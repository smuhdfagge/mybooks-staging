<div class="relative">
    <x-table-loading />

    @if($successMessage)<div class="mb-3 rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 p-3 text-sm text-green-800 dark:text-green-200">{{ $successMessage }}</div>@endif
    @if($errorMessage)<div class="mb-3 rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 p-3 text-sm text-red-800 dark:text-red-200">{{ $errorMessage }}</div>@endif

    {{-- Invoices or credit notes, and a count per status --}}
    <div class="mb-4 flex flex-wrap items-center gap-2" role="tablist" aria-label="Document type">
        <button type="button" role="tab" wire:click="$set('type', 'invoices')" aria-selected="{{ $credit ? 'false' : 'true' }}" class="px-3 py-1.5 rounded-md text-sm font-medium {{ $credit ? 'text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700' : 'bg-brand-600 text-white' }}">Invoices</button>
        <button type="button" role="tab" wire:click="$set('type', 'credit_notes')" aria-selected="{{ $credit ? 'true' : 'false' }}" class="px-3 py-1.5 rounded-md text-sm font-medium {{ $credit ? 'bg-brand-600 text-white' : 'text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700' }}">Credit notes</button>
    </div>
    <div class="mb-4 grid grid-cols-2 sm:grid-cols-5 gap-2 text-sm" data-testid="counts">
        @foreach(['not_submitted' => 'Not submitted', 'pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'failed' => 'Failed'] as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $status === $key ? '' : $key }}')" class="rounded-lg border px-3 py-2 text-left {{ $status === $key ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30' : 'border-gray-200 dark:border-gray-700' }}">
                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <span class="block text-lg font-semibold text-gray-900 dark:text-gray-100">{{ number_format($counts[$key]) }}</span>
            </button>
        @endforeach
    </div>

    <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label for="ei-search" class="form-label">Search</label>
            <input type="text" id="ei-search" wire:model.live.debounce.300ms="search" placeholder="Number, customer, IRN..." class="form-control text-sm">
        </div>
        <div>
            <label for="ei-status" class="form-label">Status</label>
            <select id="ei-status" wire:model.live="status" class="form-control text-sm">
                <option value="">All statuses</option>
                @foreach($statuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="ei-from" class="form-label">From</label>
            <input type="date" id="ei-from" wire:model.live="dateFrom" class="form-control text-sm">
        </div>
        <div>
            <label for="ei-to" class="form-label">To</label>
            <input type="date" id="ei-to" wire:model.live="dateTo" class="form-control text-sm">
        </div>
    </div>

    @if($canSubmit)
        <div class="mb-3 flex flex-wrap items-center gap-3">
            <button type="button" wire:click="submitSelected" wire:loading.attr="disabled" @disabled(! count($selectedItems)) class="btn-primary disabled:opacity-50">Send selected to NRS</button>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($selectedItems) }} selected</span>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-300">
                <tr>
                    @if($canSubmit)<th scope="col" class="px-3 py-3 w-8"><span class="sr-only">Select</span></th>@endif
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">Number</th>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">Customer</th>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">Date</th>
                    <th scope="col" class="px-4 py-3 text-right uppercase tracking-wider font-medium">Total</th>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">Type</th>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">NRS status</th>
                    <th scope="col" class="px-4 py-3 text-left uppercase tracking-wider font-medium">IRN</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                @forelse($rows as $doc)
                    @php
                        $sub = $doc->eInvoice;
                        $st = $sub?->status ?? 'not_submitted';
                    @endphp
                    <tr wire:key="ei-{{ $type }}-{{ $doc->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        @if($canSubmit)
                            <td class="px-3 py-3">
                                @if($st !== 'accepted')
                                    <input type="checkbox" wire:model.live="selectedItems" value="{{ $doc->id }}" class="rounded border-gray-300" aria-label="Select {{ $credit ? $doc->credit_note_number : $doc->invoice_number }}">
                                @endif
                            </td>
                        @endif
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ $credit ? route('credit-notes.show', $doc) : route('invoices.show', $doc) }}" class="font-medium text-brand-600 dark:text-brand-300 hover:underline">{{ $credit ? $doc->credit_note_number : $doc->invoice_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $doc->customer?->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">{{ ($credit ? $doc->credit_note_date : $doc->invoice_date)->format('M d, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-900 dark:text-gray-100">@money($doc->total)</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ strtoupper($kinds[$doc->id] ?? 'b2c') }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$st" :label="\App\Enums\EInvoiceStatus::tryFrom($st)?->label()" />
                            @if(in_array($st, ['rejected', 'failed'], true) && $sub?->last_error)
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400 max-w-xs">{{ \Illuminate\Support\Str::limit($sub->last_error, 120) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ $st === 'accepted' ? $sub->irn : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canSubmit ? 8 : 7 }}" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">Nothing here{{ $status !== '' || $search !== '' ? ' for these filters' : ' yet' }}. Invoices appear once they are sent or issued.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <label for="ei-per-page" class="sr-only">Rows per page</label>
            <select id="ei-per-page" wire:model.live="perPage" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                @foreach([10, 15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }} per page</option>@endforeach
            </select>
        </div>
        @if($rows->hasPages())<div>{{ $rows->links() }}</div>@endif
    </div>
</div>
