<?php

namespace App\Actions\SalesOrders;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\Sales\DocumentTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing a sales order, the same from the web form and the
 * API (finding R3). The web form ignored discounts and its "update" saved
 * nothing at all; the API could create an order already "completed" and
 * saved without a transaction (R6).
 *
 * Totals come from DocumentTotals, as for invoices (VAT after discounts),
 * so converting the order gives an invoice with the same figures.
 *
 * $data keys: customer_id, order_date, expected_date, reference, notes,
 * terms, discount_type, discount_amount, status, items[] (item_id,
 * description, quantity, unit_price, discount, discount_type, tax_rate).
 */
class SaveSalesOrder
{
    public const START_STATUSES = [SalesOrderStatus::Draft->value, SalesOrderStatus::Confirmed->value];

    /** Only draft and confirmed orders can be changed. */
    public const EDITABLE = [SalesOrderStatus::Draft->value, SalesOrderStatus::Confirmed->value];

    /** Status changes allowed through an update; the rest follow invoicing and delivery (the model guards them all, Q3). */
    public const TRANSITIONS = [
        'draft' => [SalesOrderStatus::Confirmed->value, SalesOrderStatus::Cancelled->value],
        'confirmed' => [SalesOrderStatus::Cancelled->value],
    ];

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): SalesOrder
    {
        $status = $data['status'] ?? 'draft';
        if (! in_array($status, self::START_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'A new sales order can only be draft or confirmed.']);
        }

        return DB::transaction(function () use ($tenantId, $data, $userId, $status) {
            $totals = $this->totals($data);

            $order = SalesOrder::create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'],
                'order_number' => SalesOrder::generateNumber($tenantId),
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'discount_type' => $totals['discount_amount'] > 0 ? ($data['discount_type'] ?? 'fixed') : null,
                'status' => $status,
                'created_by' => $userId,
            ] + $this->totalsColumns($totals));

            $this->writeLines($order, $totals['lines']);

            return $order->fresh(['items']);
        });
    }

    /** @param array<string, mixed> $data  same keys as create(); header fields left out keep their values */
    public function update(SalesOrder $order, array $data): SalesOrder
    {
        if (! in_array($order->status, self::EDITABLE, true)) {
            throw ValidationException::withMessages(['sales_order' => "A {$order->status} sales order can't be changed."]);
        }
        if (isset($data['status']) && $data['status'] !== $order->status
            && ! in_array($data['status'], self::TRANSITIONS[$order->status], true)) {
            throw ValidationException::withMessages(['status' => "A {$order->status} sales order can't be marked {$data['status']}."]);
        }

        return DB::transaction(function () use ($order, $data) {
            $lines = $data['items'] ?? $order->items()->get()
                ->map(fn ($l) => $l->only(['item_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_rate']))->all();

            // No new document discount sent: keep the current one (stored as money).
            if (! array_key_exists('discount_amount', $data)) {
                $data['discount_type'] = 'fixed';
                $data['discount_amount'] = $order->discount_amount;
            }
            $totals = $this->totals(['items' => $lines] + $data);

            $order->update(array_filter([
                'customer_id' => $data['customer_id'] ?? null,
                'order_date' => $data['order_date'] ?? null,
                'status' => $data['status'] ?? null,
            ]) + array_intersect_key($data, array_flip(['expected_date', 'reference', 'notes', 'terms'])) + [
                'discount_type' => $totals['discount_amount'] > 0 ? ($data['discount_type'] ?? 'fixed') : null,
            ] + $this->totalsColumns($totals));

            $order->items()->delete();
            $this->writeLines($order, $totals['lines']);

            return $order->fresh(['items']);
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
    protected function writeLines(SalesOrder $order, array $lines): void
    {
        foreach ($lines as $line) {
            SalesOrderItem::create([
                'sales_order_id' => $order->id,
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
