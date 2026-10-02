{{-- Quotation form, shared by create and edit. --}}
@php
    $q = $quotation ?? null;
    $lines = old('items', $q ? $q->items->map(fn ($l) => [
        'item_id' => $l->item_id, 'item_name' => $l->item?->name, 'description' => $l->description,
        'quantity' => $l->quantity, 'unit_price' => $l->unit_price, 'tax_rate' => $l->tax_rate, 'discount' => $l->discount,
    ])->all() : []);
@endphp

<x-card>
    <div class="p-4 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Quotation details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label for="quotation_number" class="form-label">Quotation number</label>
                <input type="text" id="quotation_number" value="{{ $q->quotation_number ?? $quotationNumber }}" disabled class="form-control bg-gray-100 dark:bg-gray-600">
            </div>
            <x-customer-picker :options="$customerOptions" :value="old('customer_id', $q->customer_id ?? request('customer_id'))" />
            <div>
                <x-field name="quotation_date" label="Quotation date" type="date" required :value="old('quotation_date', $q?->quotation_date?->toDateString() ?? date('Y-m-d'))" />
            </div>
            <div>
                <x-field name="expiry_date" label="Valid until" type="date" :value="old('expiry_date', $q?->expiry_date?->toDateString() ?? now()->addDays(30)->toDateString())"
                    help="After this date the quotation is marked expired." />
            </div>
        </div>
        <div class="mt-6 md:w-1/2">
            <x-field name="reference" label="Reference" :value="old('reference', $q->reference ?? '')" maxlength="100" />
        </div>
    </div>
</x-card>

<x-sales-lines :lines="$lines" :discount-type="old('discount_type', $q && (float) $q->discount_amount > 0 ? 'fixed' : '')"
    :discount-value="old('discount_amount', $q->discount_amount ?? 0)" summary-title="Quotation summary">
    <x-card>
        <div class="p-4 sm:p-6 space-y-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-200 dark:border-gray-700">Notes &amp; terms</h3>
            <div><x-field name="notes" label="Notes (shown on the quotation)" type="textarea" rows="3" :value="old('notes', $q->notes ?? '')" /></div>
            <div><x-field name="terms" label="Terms" type="textarea" rows="3" :value="old('terms', $q->terms ?? '')" /></div>
        </div>
    </x-card>
</x-sales-lines>
