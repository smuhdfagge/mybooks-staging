<?php

namespace App\Actions\StockTransfers;

use App\Enums\StockTransferStatus;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shipping a stock transfer (session 13): the goods leave the source
 * warehouse on the transfer date.
 *
 * - Only free stock can go (on hand less what is reserved for invoices),
 *   checked per item in the source warehouse. The old code didn't check,
 *   so stock could go negative.
 * - The cost leaves with the goods through StockValuationService::issue(),
 *   so it follows the item's FIFO layers or weighted average in that
 *   warehouse, recorded against the transfer line. The old code moved no
 *   cost until the goods were received.
 * - No journal: both warehouses share the Inventory account, so the
 *   business's stock value doesn't change.
 */
class ShipStockTransfer
{
    use MovesTransferStock;

    public function handle(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer = StockTransfer::withoutGlobalScopes()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (! $transfer->isDraft()) {
                throw ValidationException::withMessages(['transfer' => "Transfer {$transfer->transfer_number} has already been shipped."]);
            }
            $this->assertDateOpen($transfer, $transfer->transfer_date, 'transfer_date');
            [$from, $to] = self::checkWarehouses($transfer->tenant_id, $transfer->from_warehouse_id, $transfer->to_warehouse_id);

            $lines = $transfer->items()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one item to send.']);
            }
            $items = Item::withoutGlobalScopes()->where('tenant_id', $transfer->tenant_id)->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');

            // Free stock in the source warehouse, per item.
            foreach ($lines->groupBy('item_id') as $itemId => $group) {
                $item = $items->get($itemId);
                if (! $item || ! $item->track_inventory) {
                    throw ValidationException::withMessages(['items' => ($item->name ?? 'This item')." doesn't keep stock, so it can't be transferred."]);
                }
                $wanted = (float) $group->sum('quantity');
                $row = $this->valuation()->stockRow($transfer->tenant_id, (int) $itemId, $from->id, false);
                $onHand = (float) ($row->quantity ?? 0);
                $reserved = (float) ($row->reserved_quantity ?? 0);
                $free = max(0, $onHand - $reserved);
                if ($wanted - $free > 0.00001) {
                    $why = $reserved > 0 ? " ({$this->qty($onHand)} on hand, {$this->qty($reserved)} reserved for invoices)" : '';
                    throw ValidationException::withMessages(['items' => "Only {$this->qty($free)} {$item->name} free in {$from->name}{$why}, so you can't send {$this->qty($wanted)}."]);
                }
            }

            foreach ($lines as $line) {
                $item = $items->get($line->item_id);
                $quantity = (float) $line->quantity;

                $row = $this->valuation()->stockRow($transfer->tenant_id, $item->id, $from->id);
                $qtyBefore = (float) $row->quantity;
                $avgBefore = (float) $row->unit_cost;

                $cost = $this->valuation()->issue($item, $quantity, StockTransferItem::class, $line->id, false, $from->id);

                $left = round($qtyBefore - $quantity, 4);
                $row->quantity = $left;
                if ($left > 0.00001) {
                    // Average cost of what stays behind.
                    $row->unit_cost = round(max(0, ($qtyBefore * $avgBefore - $cost) / $left), 4);
                    $this->averageFromLayers($row);
                }
                $row->save();

                $this->history($transfer, $item->id, $from->id, -$quantity, "Transfer {$transfer->transfer_number} to {$to->name}");

                $line->shipped_cost = $cost;
                $line->save();
            }

            $transfer->status = StockTransferStatus::InTransit->value;
            $transfer->shipped_at = now();
            $transfer->save();

            return $transfer->fresh(['items']);
        });
    }
}
