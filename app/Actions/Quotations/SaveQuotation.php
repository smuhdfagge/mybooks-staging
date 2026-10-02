<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Sales\DocumentTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing a quotation. Lines and totals follow the same rules
 * as invoices and sales orders (DocumentTotals: VAT after discounts), so a
 * quotation converts to an order or invoice with the same figures.
 *
 * $data keys: customer_id, quotation_date, expiry_date, reference, notes,
 * terms, discount_type, discount_amount, items[] (item_id, description,
 * quantity, unit_price, discount, discount_type, tax_rate).
 */
class SaveQuotation
{
    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): Quotation
    {
        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $totals = $this->totals($data);

            $quotation = Quotation::create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'],
                'quotation_number' => Quotation::generateNumber($tenantId),
                'quotation_date' => $data['quotation_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'discount_type' => $totals['discount_amount'] > 0 ? ($data['discount_type'] ?? 'fixed') : null,
                'status' => QuotationStatus::Draft->value,
                'created_by' => $userId,
            ] + $this->totalsColumns($totals));

            $this->writeLines($quotation, $totals['lines']);

            return $quotation->fresh(['items']);
        });
    }

    /**
     * Change a quotation. A rejected or expired quotation that is changed
     * becomes a draft again, ready to be re-sent.
     *
     * @param  array<string, mixed>  $data  same keys as create()
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        if (! in_array($quotation->status, QuotationStatus::editableValues(), true)) {
            throw ValidationException::withMessages(['quotation' => "A {$quotation->status} quotation can't be changed."]);
        }

        return DB::transaction(function () use ($quotation, $data) {
            $totals = $this->totals($data);

            $status = in_array($quotation->status, [QuotationStatus::Rejected->value, QuotationStatus::Expired->value], true)
                ? QuotationStatus::Draft->value
                : $quotation->status;

            $quotation->update([
                'customer_id' => $data['customer_id'],
                'quotation_date' => $data['quotation_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'discount_type' => $totals['discount_amount'] > 0 ? ($data['discount_type'] ?? 'fixed') : null,
                'status' => $status,
            ] + $this->totalsColumns($totals));

            $quotation->items()->delete();
            $this->writeLines($quotation, $totals['lines']);

            return $quotation->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lines: array<int|string, array<string, mixed>>, subtotal: float, discount_amount: float, tax_amount: float, total: float}
     */
    protected function totals(array $data): array
    {
        return DocumentTotals::calculate($data['items'], $data['discount_type'] ?? null, $data['discount_amount'] ?? 0);
    }

    /** @param array<int|string, array<string, mixed>> $lines */
    protected function writeLines(Quotation $quotation, array $lines): void
    {
        foreach ($lines as $line) {
            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'item_id' => $line['item_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => $line['discount'] ?? 0,
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'total' => $line['total'],
            ]);
        }
    }

    /**
     * @param  array{subtotal: float, discount_amount: float, tax_amount: float, total: float}  $totals
     * @return array<string, float>
     */
    protected function totalsColumns(array $totals): array
    {
        return [
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
        ];
    }
}
