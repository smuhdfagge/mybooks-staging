<?php

namespace App\Actions\Quotations;

use App\Actions\Invoices\SaveInvoice;
use App\Actions\SalesOrders\SaveSalesOrder;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turning a quotation into a sales order (SaveSalesOrder) or straight into
 * an invoice (SaveInvoice), so the new document follows the same rules as
 * one typed in by hand: same totals, and for invoices the stock check and
 * reservation. Line and document discounts are stored as money on the
 * quotation, so they are passed on as fixed amounts.
 */
class ConvertQuotation
{
    public function __construct(
        private SaveSalesOrder $saveOrder,
        private SaveInvoice $saveInvoice,
    ) {}

    public function toSalesOrder(Quotation $quotation, ?int $userId = null): SalesOrder
    {
        $this->assertConvertible($quotation);

        return DB::transaction(function () use ($quotation, $userId) {
            $order = $this->saveOrder->create($quotation->tenant_id, [
                'customer_id' => $quotation->customer_id,
                'reference' => "From {$quotation->quotation_number}",
                'order_date' => now()->toDateString(),
                'expected_date' => null,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'discount_type' => 'fixed',
                'discount_amount' => $quotation->discount_amount ?? 0,
                'items' => $this->lines($quotation),
            ], $userId);

            $quotation->update([
                'status' => QuotationStatus::Converted->value,
                'converted_to_so_id' => $order->id,
            ]);

            return $order;
        });
    }

    public function toInvoice(Quotation $quotation, ?int $userId = null): Invoice
    {
        $this->assertConvertible($quotation);

        return DB::transaction(function () use ($quotation, $userId) {
            $quotation->loadMissing('customer');
            $days = (int) ($quotation->customer?->payment_terms ?: 30);

            $invoice = $this->saveInvoice->create($quotation->tenant_id, [
                'customer_id' => $quotation->customer_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays($days)->toDateString(),
                'reference' => $quotation->reference ?: $quotation->quotation_number,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'discount_type' => 'fixed',
                'discount_amount' => $quotation->discount_amount ?? 0,
                'items' => $this->lines($quotation),
            ], $userId);

            $quotation->update([
                'status' => QuotationStatus::Converted->value,
                'converted_to_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    protected function assertConvertible(Quotation $quotation): void
    {
        if (! in_array($quotation->status, QuotationStatus::convertibleValues(), true)) {
            $why = $quotation->status === QuotationStatus::Expired->value
                ? 'It has expired: change its expiry date first.'
                : "It is {$quotation->status}.";
            throw ValidationException::withMessages(['quotation' => "Quotation {$quotation->quotation_number} can't be converted. {$why}"]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function lines(Quotation $quotation): array
    {
        return $quotation->items()->get()->map(fn ($i) => [
            'item_id' => $i->item_id,
            'description' => $i->description,
            'quantity' => $i->quantity,
            'unit_price' => $i->unit_price,
            'discount' => $i->discount ?? 0,
            'discount_type' => 'fixed',
            'tax_rate' => $i->tax_rate ?? 0,
        ])->all();
    }
}
