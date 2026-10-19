{{--
    Invoice template preview (Settings > Invoice templates). It draws the real
    invoice document with a sample invoice, so what is designed here is
    exactly what customers receive, prints and goes into the PDF (invoice
    rebuild). Pass $settings (the template's settings).
--}}
@php
    $previewTenant = $tenant ?? auth()->user()?->tenant ?? (new \App\Models\Tenant)->forceFill(['name' => 'Your business', 'currency' => 'NGN']);
    $sample = new \App\Models\Invoice;
    $sample->forceFill([
        'tenant_id' => $previewTenant->id ?? 0,
        'invoice_number' => 'INV-000123',
        'reference' => 'PO 4471',
        'invoice_date' => now()->subDays(3),
        'due_date' => now()->addDays(27),
        'status' => 'sent',
        'subtotal' => 1425000, 'tax_amount' => 106875, 'discount_amount' => 0, 'total' => 1531875,
        'amount_paid' => 0, 'balance_due' => 1531875,
        'notes' => 'Goods remain ours until paid in full.',
        'terms' => 'Payment within 30 days by bank transfer.',
    ]);
    $sample->setRelation('customer', (new \App\Models\Customer)->forceFill([
        'name' => 'Danjuma Foods', 'billing_address' => '12 Bompai Road', 'city' => 'Kano', 'state' => 'Kano',
        'email' => 'accounts@danjumafoods.ng', 'phone' => '0803 123 4567',
    ]));
    $sample->setRelation('items', collect([
        ['Rice 50kg', 20, 52000, 7.5, 78000],
        ['Groundnut oil 25L', 10, 38500, 7.5, 28875],
    ])->map(fn ($l) => (new \App\Models\InvoiceItem)->forceFill([
        'description' => $l[0], 'quantity' => $l[1], 'unit_price' => $l[2], 'tax_rate' => $l[3],
        'tax_amount' => $l[4], 'total' => $l[1] * $l[2] + $l[4],
    ])->setRelation('item', null)));
@endphp
<div class="overflow-hidden bg-white shadow-sm ring-1 ring-gray-900/5">
    @include('invoices.document', \App\Support\InvoiceDocument::data($sample, $previewTenant, false, $settings ?? []))
</div>
