<?php

namespace App\Actions\Bills;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\PurchaseOrder;
use App\Services\Sales\DocumentTotals;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing a bill, the same from the web form, the API and
 * recurring bills (finding R3). The web applied line discounts only; the
 * API took a document discount after VAT, accepted any status (even
 * "paid") and saved without a transaction (R6).
 *
 * In one transaction: work out lines and totals (VAT after discounts),
 * save the bill and lines, then post it (journal split by line, goods into
 * stock, A21). Billing a purchase order locks the order and marks it billed.
 *
 * Totals: subtotal is quantity x price before discounts, discount_amount
 * is line discounts plus the document discount, and each line's total is
 * what it costs after both discounts, plus its VAT. That is what the bill
 * journal reads.
 *
 * $data keys: vendor_id, bill_date, due_date, reference (vendor's bill
 * number), notes, discount_amount (document discount, money),
 * purchase_order_id, status (draft or unpaid; default unpaid),
 * recurrent_bill_id, items[] (item_id, account_id, description, quantity,
 * unit_price, discount (money), tax_rate).
 */
class SaveBill
{
    public const START_STATUSES = [BillStatus::Draft->value, BillStatus::Unpaid->value];

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): Bill
    {
        $status = $data['status'] ?? 'unpaid';
        if (! in_array($status, self::START_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'A new bill can only be draft or unpaid; payments mark it paid.']);
        }

        return DB::transaction(function () use ($tenantId, $data, $userId, $status) {
            $order = $this->lockPurchaseOrder($data);
            $totals = $this->totals($data);

            $bill = Bill::withoutEvents(fn () => Bill::create([
                'tenant_id' => $tenantId,
                'vendor_id' => $data['vendor_id'],
                'purchase_order_id' => $order?->id,
                'recurrent_bill_id' => $data['recurrent_bill_id'] ?? null,
                'bill_number' => Bill::generateNumber($tenantId),
                'vendor_bill_number' => $data['reference'] ?? $data['vendor_bill_number'] ?? null,
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'],
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total' => 0,
                'amount_paid' => 0, 'balance_due' => 0,
                'created_by' => $userId,
            ]));

            $this->writeLines($bill, $totals['lines']);
            Bill::withoutEvents(fn () => $bill->update($this->totalsColumns($totals, 0.0)));
            $order?->update(['status' => PurchaseOrder::STATUS_BILLED]);

            $bill->postWithLines();

            return $bill->fresh(['items']);
        });
    }

    /** @param array<string, mixed> $data  same keys as create(); header fields left out keep their values */
    public function update(Bill $bill, array $data): Bill
    {
        if (in_array($bill->status, ['paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['bill' => "A {$bill->status} bill can't be changed."]);
        }

        return DB::transaction(function () use ($bill, $data) {
            $lines = $data['items'] ?? $bill->items()->get()
                ->map(fn ($l) => $l->only(['item_id', 'account_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_rate']))->all();

            if ($bill->inventory_updated_at && $this->stockLines($lines) !== $this->stockLines($bill->items()->get()->toArray())) {
                throw ValidationException::withMessages(['items' => 'The goods on this bill are already in stock, so their items and quantities can\'t change. Prices, descriptions and other lines can.']);
            }

            if (! array_key_exists('discount_amount', $data)) {
                $data['discount_amount'] = max(0, (float) $bill->discount_amount - (float) $bill->items()->sum('discount'));
            }
            $totals = $this->totals(['items' => $lines] + $data);

            // Payments (with WHT) and supplier credits used count as paid.
            $paid = round($bill->settledByPayments() + (float) $bill->vendorCreditApplications()->sum('amount'), 2);
            if ($paid > $totals['total'] + 0.005) {
                throw ValidationException::withMessages(['items' => 'The new total is less than what has already been paid ('.number_format($paid, 2).').']);
            }

            Bill::withoutEvents(fn () => $bill->update(array_filter([
                'vendor_id' => $data['vendor_id'] ?? null,
                'bill_date' => $data['bill_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
            ]) + array_intersect_key($data, array_flip(['notes'])) + (array_key_exists('reference', $data) ? ['vendor_bill_number' => $data['reference']] : [])
               + $this->totalsColumns($totals, $paid)));

            $bill->items()->delete();
            $this->writeLines($bill, $totals['lines']);

            $bill->postWithLines();

            return $bill->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lines: array<int|string, array<string, mixed>>, subtotal: float, discount_amount: float, tax_amount: float, total: float}
     */
    protected function totals(array $data): array
    {
        $lines = array_map(fn ($l) => $l + ['discount_type' => 'fixed'], $data['items']);

        return DocumentTotals::calculate($lines, 'fixed', $data['discount_amount'] ?? 0);
    }

    /** @param array<int|string, array<string, mixed>> $lines */
    protected function writeLines(Bill $bill, array $lines): void
    {
        foreach ($lines as $line) {
            BillItem::create([
                'bill_id' => $bill->id,
                'item_id' => $line['item_id'] ?? null,
                'account_id' => $line['account_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => $line['discount'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                // After both discounts, plus VAT: what the journal and stock cost read.
                'total' => Money::subtract($line['total'], $line['discount_share']),
            ]);
        }
    }

    /**
     * @param  array{lines: array<int|string, array<string, mixed>>, subtotal: float, discount_amount: float, tax_amount: float, total: float}  $totals
     * @return array<string, float>
     */
    protected function totalsColumns(array $totals, float $paid): array
    {
        $gross = Money::sum(array_map(fn ($l) => (float) $l['quantity'] * (float) $l['unit_price'], $totals['lines']));
        $lineDiscounts = Money::sum(array_column($totals['lines'], 'discount'));

        return [
            'subtotal' => $gross,
            'discount_amount' => Money::add($lineDiscounts, $totals['discount_amount']),
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'amount_paid' => $paid,
            'balance_due' => Money::subtract($totals['total'], $paid),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function lockPurchaseOrder(array $data): ?PurchaseOrder
    {
        if (empty($data['purchase_order_id'])) {
            return null;
        }

        // Locked so two people can't bill the same order at once.
        $order = PurchaseOrder::lockForUpdate()->find($data['purchase_order_id']);
        if (! $order || (int) $order->vendor_id !== (int) $data['vendor_id']) {
            throw ValidationException::withMessages(['vendor_id' => "The vendor must be the purchase order's vendor."]);
        }
        if (! in_array($order->status, PurchaseOrder::BILLABLE, true)) {
            throw ValidationException::withMessages(['purchase_order_id' => 'This purchase order has already been billed or cannot be billed.']);
        }

        return $order;
    }

    /**
     * Stocked goods on a bill as "item:quantity" pairs, sorted, to compare.
     *
     * @param  array<int|string, array<string, mixed>>  $lines
     * @return array<int, string>
     */
    protected function stockLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! empty($line['item_id'])) {
                $out[] = (int) $line['item_id'].':'.number_format((float) $line['quantity'], 4, '.', '');
            }
        }
        sort($out);

        return $out;
    }
}
