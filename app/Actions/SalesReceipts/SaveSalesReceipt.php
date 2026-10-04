<?php

namespace App\Actions\SalesReceipts;

use App\Models\Inventory;
use App\Models\InventoryLayerConsumption;
use App\Models\Item;
use App\Models\SalesReceipt;
use App\Models\SalesReceiptItem;
use App\Services\Sales\DocumentTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing a cash sale (finding R3). Totals come from
 * DocumentTotals, as for invoices and sales orders: VAT after discounts.
 * Cash sales used to ignore VAT and discounts altogether, so VAT on a cash
 * sale was never charged or reported.
 *
 * In one transaction: check there is enough unreserved stock (the goods
 * leave now), save the receipt and lines, then save the totals, which
 * posts the journal (cash, revenue, VAT, cost of sales).
 *
 * $data keys: customer_id, receipt_date, payment_method, reference, notes,
 * discount_type, discount_amount, items[] (item_id, description, quantity,
 * unit_price, discount, discount_type, tax_rate).
 */
class SaveSalesReceipt
{
    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): SalesReceipt
    {
        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $this->assertStockAvailable($tenantId, $data['items']);
            $totals = $this->totals($data);

            $receipt = SalesReceipt::create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'] ?? null,
                'receipt_number' => SalesReceipt::generateNumber($tenantId),
                'receipt_date' => $data['receipt_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->writeLines($receipt, $totals['lines']);
            // Saving the totals posts the journal, now the lines exist.
            $receipt->update($this->totalsColumns($totals));

            return $receipt->fresh(['items']);
        });
    }

    /** @param array<string, mixed> $data  same keys as create() */
    public function update(SalesReceipt $receipt, array $data): SalesReceipt
    {
        return DB::transaction(function () use ($receipt, $data) {
            $this->assertStockAvailable($receipt->tenant_id, $data['items'], $receipt);

            // No new document discount sent: keep the current one (stored as money).
            if (! array_key_exists('discount_amount', $data)) {
                $data['discount_type'] = 'fixed';
                $data['discount_amount'] = $receipt->discount_amount;
            }
            $totals = $this->totals($data);

            $receipt->fill([
                'customer_id' => $data['customer_id'] ?? null,
                'receipt_date' => $data['receipt_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            // Saved with events off: the old and new dates are checked here (session 11).
            $receipt->assertPeriodAllowsSave();
            SalesReceipt::withoutEvents(fn () => $receipt->save());

            $receipt->items()->delete();
            $this->writeLines($receipt, $totals['lines']);
            // Re-posts the journal and re-issues the stock at today's cost.
            $receipt->update($this->totalsColumns($totals));

            return $receipt->fresh(['items']);
        });
    }

    /**
     * A cash sale hands the goods over at once, so there must be enough
     * unreserved stock. Stock this receipt already took (when editing)
     * counts as available again. Rows stay locked until the receipt is saved.
     *
     * @param  array<int|string, array<string, mixed>>  $lines
     */
    public function assertStockAvailable(int $tenantId, array $lines, ?SalesReceipt $receipt = null): void
    {
        $needed = [];
        foreach ($lines as $index => $line) {
            if (! empty($line['item_id'])) {
                $needed[$line['item_id']][] = [$index, (float) $line['quantity']];
            }
        }

        $errors = [];
        foreach ($needed as $itemId => $uses) {
            $item = Item::find($itemId);
            if (! $item || ! $item->track_inventory || $item->type === 'service') {
                continue;
            }

            $inventory = Inventory::where('tenant_id', $tenantId)->where('item_id', $itemId)->lockForUpdate()->first();
            $available = $inventory ? (float) $inventory->available_quantity : 0.0;
            if ($receipt) {
                $available += (float) InventoryLayerConsumption::where('source_type', SalesReceipt::class)
                    ->where('source_id', $receipt->id)->where('item_id', $itemId)->where('reduced_on_hand', true)->sum('quantity');
            }

            $total = array_sum(array_column($uses, 1));
            if ($total - $available > 0.00001) {
                $errors["items.{$uses[0][0]}.quantity"] = "Not enough stock for '{$item->name}'. Available: ".rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.').", requested: {$total}.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
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
    protected function writeLines(SalesReceipt $receipt, array $lines): void
    {
        foreach ($lines as $line) {
            SalesReceiptItem::create([
                'sales_receipt_id' => $receipt->id,
                'item_id' => $line['item_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => $line['discount'] ?? 0,
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'vat_treatment' => $line['vat_treatment'] ?? null,
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
