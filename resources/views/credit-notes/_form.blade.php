{{-- Credit note form, shared by create and edit. --}}
@php
    $cn = $creditNote ?? null;
    $invoice = $invoice ?? $cn?->invoice;
    $startLines = old('items', $cn ? $cn->items->map(fn ($l) => [
        'item_id' => $l->item_id, 'item_name' => $l->item?->name, 'description' => $l->description,
        'quantity' => $l->quantity, 'unit_price' => $l->unit_price, 'tax_rate' => $l->tax_rate,
    ])->all() : ($lines ?? []));
@endphp

<x-error-summary />
<x-lock-date-notice field="credit_note_date" />

<x-card>
    <div class="p-4 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Credit note details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label for="credit_note_number" class="form-label">Credit note number</label>
                <input type="text" id="credit_note_number" value="{{ $cn->credit_note_number ?? $creditNoteNumber }}" disabled class="form-control bg-gray-100 dark:bg-gray-600">
            </div>
            <x-customer-picker :options="$customerOptions" :value="old('customer_id', $cn->customer_id ?? $invoice?->customer_id ?? request('customer_id'))" :locked="(bool) $invoice" />
            <div>
                <x-field name="credit_note_date" label="Date" type="date" required :value="old('credit_note_date', $cn?->credit_note_date?->toDateString() ?? date('Y-m-d'))" />
            </div>
            <div>
                <x-field name="reason" label="Reason" type="select">
                    <option value="">Choose...</option>
                    @foreach($reasons as $value => $label)
                        <option value="{{ $value }}" @selected(old('reason', $cn->reason ?? ($invoice ? 'product_return' : '')) === $value)>{{ $label }}</option>
                    @endforeach
                </x-field>
            </div>
        </div>

        @if($invoice)
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">Against invoice <a href="{{ route('invoices.show', $invoice) }}" class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $invoice->invoice_number }}</a> ({{ $invoice->invoice_date->format('M d, Y') }}, @money($invoice->total)). The lines below are what is left to credit on it: remove or reduce the ones that are not being credited.</p>
            @error('invoice_id')<p class="form-error">{{ $message }}</p>@enderror
        @endif

        <div class="mt-4 flex items-start gap-3">
            <input type="hidden" name="restock" value="0">
            <input type="checkbox" id="restock" name="restock" value="1" @checked(old('restock', $cn->restock ?? false))
                class="mt-1 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700">
            <div>
                <label for="restock" class="text-sm font-medium text-gray-900 dark:text-gray-100">The customer returned the goods</label>
                <p class="form-help">Stock items on this credit note go back into stock, at what they cost you, when it is posted.</p>
                @error('restock')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
</x-card>

<x-sales-lines :lines="$startLines" :document-discount="false" title="What is being credited" summary-title="Credit summary">
    <x-card>
        <div class="p-4 sm:p-6">
            <x-field name="notes" label="Notes (shown on the credit note)" type="textarea" rows="4" :value="old('notes', $cn->notes ?? '')" />
        </div>
    </x-card>
</x-sales-lines>
