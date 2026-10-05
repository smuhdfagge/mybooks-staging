<?php

namespace App\Actions\StockTransfers;

use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\InventoryLayerConsumption;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\Accounting\LockDates;
use App\Services\StockValuationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Shared steps for the stock transfer actions (session 13).
 *
 * Shipping takes the goods out of the source warehouse's cost layers with
 * StockValuationService::issue(), recorded against the transfer line
 * (inventory_layer_consumptions). Those pieces are what the goods cost:
 * receiving puts the same cost into the destination, and cancelling or
 * sending a shortfall back puts each piece back in the layer it came from.
 */
trait MovesTransferStock
{
    protected function valuation(): StockValuationService
    {
        return app(StockValuationService::class);
    }

    /** Lock dates and closed periods (session 11) apply to every step. */
    protected function assertDateOpen(StockTransfer $transfer, mixed $date, string $key): void
    {
        if ($reason = LockDates::instance()->blockReason($date, (int) $transfer->tenant_id, auth()->user())) {
            throw ValidationException::withMessages([$key => $reason]);
        }
    }

    /**
     * Both warehouses must be this business's, in use, and different.
     *
     * @return array{0: Warehouse, 1: Warehouse}
     */
    public static function checkWarehouses(int $tenantId, mixed $fromId, mixed $toId): array
    {
        $find = fn ($id) => $id ? Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey((int) $id)->first() : null;
        $from = $find($fromId);
        $to = $find($toId);

        $errors = [];
        if (! $from) {
            $errors['from_warehouse_id'] = 'Choose one of your own warehouses to send from.';
        } elseif (! $from->is_active) {
            $errors['from_warehouse_id'] = "{$from->name} is not in use any more.";
        }
        if (! $to) {
            $errors['to_warehouse_id'] = 'Choose one of your own warehouses to send to.';
        } elseif (! $to->is_active) {
            $errors['to_warehouse_id'] = "{$to->name} is not in use any more.";
        } elseif ($from && $from->id === $to->id) {
            $errors['to_warehouse_id'] = 'Choose a different warehouse to send the goods to.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [$from, $to];
    }

    /**
     * Split a line's pieces into the first $quantity units and the rest.
     * Each part is a list of [consumption row, quantity].
     *
     * @param  Collection<int, InventoryLayerConsumption>  $rows
     * @return array{0: array<int, array{0: InventoryLayerConsumption, 1: float}>, 1: array<int, array{0: InventoryLayerConsumption, 1: float}>}
     */
    protected function splitPieces(Collection $rows, float $quantity): array
    {
        $first = [];
        $rest = [];
        $left = $quantity;
        foreach ($rows as $row) {
            $qty = (float) $row->quantity;
            $take = max(0, min($qty, $left));
            if ($take > 0.00001) {
                $first[] = [$row, round($take, 4)];
            }
            if ($qty - $take > 0.00001) {
                $rest[] = [$row, round($qty - $take, 4)];
            }
            $left -= $take;
        }

        return [$first, $rest];
    }

    /** @param  array<int, array{0: InventoryLayerConsumption, 1: float}>  $pieces */
    protected function piecesCost(array $pieces): float
    {
        return round(array_sum(array_map(fn ($p) => $p[1] * (float) $p[0]->unit_cost, $pieces)), 2);
    }

    /**
     * Put pieces back in the source warehouse, each in the cost layer it
     * came from (a new layer at the same cost if it came from none), and
     * add the quantity back on hand. Returns their cost.
     *
     * @param  array<int, array{0: InventoryLayerConsumption, 1: float}>  $pieces
     */
    protected function returnPieces(StockTransfer $transfer, Item $item, array $pieces, string $note): float
    {
        $quantity = 0.0;
        foreach ($pieces as [$row, $qty]) {
            if ($row->inventory_layer_id) {
                InventoryLayer::withoutGlobalScopes()->whereKey($row->inventory_layer_id)->increment('remaining_quantity', $qty);
            } else {
                $this->valuation()->addLayer($transfer->tenant_id, $item->id, $qty, (float) $row->unit_cost,
                    $transfer->from_warehouse_id, 'stock_transfer', $transfer->id);
            }
            if ($qty >= (float) $row->quantity - 0.00001) {
                $row->delete();
            } else {
                $row->quantity = round((float) $row->quantity - $qty, 4);
                $row->save();
            }
            $quantity += $qty;
        }
        $cost = $this->piecesCost($pieces);

        if ($quantity > 0) {
            $this->addOnHand($transfer->tenant_id, $item->id, (int) $transfer->from_warehouse_id, $quantity, $cost);
            $this->history($transfer, $item->id, (int) $transfer->from_warehouse_id, $quantity, $note);
        }

        return $cost;
    }

    /** Add goods to a warehouse's stock record, keeping its average cost right. */
    protected function addOnHand(int $tenantId, int $itemId, int $warehouseId, float $quantity, float $cost): void
    {
        $row = $this->valuation()->stockRow($tenantId, $itemId, $warehouseId);
        $this->valuation()->updateWeightedAverageCost($row, $quantity, $quantity > 0 ? $cost / $quantity : 0);
        $row->quantity = round((float) $row->quantity + $quantity, 4);
        $this->averageFromLayers($row);
        $row->save();
    }

    /**
     * When the warehouse's cost layers hold exactly the stock on hand, take
     * the average cost from them, so rounding doesn't build up over moves.
     */
    protected function averageFromLayers(Inventory $row): void
    {
        $layers = InventoryLayer::withoutGlobalScopes()->where('tenant_id', $row->tenant_id)
            ->forItem((int) $row->item_id, (int) $row->warehouse_id)->where('remaining_quantity', '>', 0)
            ->selectRaw('SUM(remaining_quantity) as qty, SUM(remaining_quantity * unit_cost) as value')->toBase()->first();
        $qty = (float) ($layers->qty ?? 0);
        if ($qty > 0.00001 && abs($qty - (float) $row->quantity) < 0.0001) {
            $row->unit_cost = round((float) $layers->value / $qty, 4);
        }
    }

    protected function history(StockTransfer $transfer, int $itemId, int $warehouseId, float $quantity, string $note): void
    {
        $history = new InventoryHistory([
            'tenant_id' => $transfer->tenant_id,
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'type' => 'transfer',
            'quantity' => round($quantity, 4),
            'reference_type' => 'stock_transfer',
            'reference_id' => $transfer->id,
            'notes' => $note,
            'created_by' => auth()->id(),
        ]);
        $history->skipTenantGuard = true;
        $history->save();
    }

    protected function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ','), '0'), '.');
    }
}
