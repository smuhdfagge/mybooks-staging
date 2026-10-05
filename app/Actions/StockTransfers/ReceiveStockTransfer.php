<?php

namespace App\Actions\StockTransfers;

use App\Enums\StockTransferStatus;
use App\Models\InventoryLayerConsumption;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Receiving a shipped stock transfer (session 13).
 *
 * The goods go into the destination warehouse at exactly the cost that
 * left the source: for FIFO items a cost layer for each source layer (same
 * cost, same purchase date, so the oldest goods are still sold first); for
 * weighted average items one layer at the average they left at.
 *
 * Fewer can be received than were sent. The shortfall either goes back to
 * the source warehouse (the default; each piece back in the layer it came
 * from) or is written off as lost: Dr Stock losses, Cr Inventory at the
 * lost goods' cost. That is the only journal a transfer posts.
 *
 * $received: [transfer line id => quantity received]; a line not given is
 * received in full. $shortfall: 'return' or 'lost'.
 */
class ReceiveStockTransfer
{
    use MovesTransferStock;

    public const RETURN = 'return';

    public const LOST = 'lost';

    public function __construct(private JournalService $journals) {}

    /** @param array<int, mixed> $received */
    public function handle(StockTransfer $transfer, array $received = [], string $shortfall = self::RETURN, mixed $date = null): StockTransfer
    {
        if (! in_array($shortfall, [self::RETURN, self::LOST], true)) {
            throw ValidationException::withMessages(['shortfall' => 'Choose what happened to the goods that did not arrive.']);
        }

        return DB::transaction(function () use ($transfer, $received, $shortfall, $date) {
            $transfer = StockTransfer::withoutGlobalScopes()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (! $transfer->isInTransit()) {
                throw ValidationException::withMessages(['transfer' => $transfer->isDraft()
                    ? "Ship transfer {$transfer->transfer_number} before receiving it."
                    : "Transfer {$transfer->transfer_number} is {$transfer->status}, so it can't be received."]);
            }

            $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
            if ($date < $transfer->transfer_date->toDateString()) {
                throw ValidationException::withMessages(['received_date' => 'The goods can\'t arrive before they were sent ('.$transfer->transfer_date->format('j M Y').').']);
            }
            $this->assertDateOpen($transfer, $date, 'received_date');

            $transfer->load(['fromWarehouse', 'toWarehouse']);
            $from = $transfer->fromWarehouse;
            $to = $transfer->toWarehouse;
            $lines = $transfer->items()->get();
            $items = Item::withoutGlobalScopes()->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');

            $lostLines = [];
            foreach ($lines as $index => $line) {
                $item = $items->get($line->item_id);
                $sent = (float) $line->quantity;
                $got = array_key_exists($line->id, $received) && $received[$line->id] !== null && $received[$line->id] !== ''
                    ? round((float) $received[$line->id], 4) : $sent;
                if ($got < 0 || $got - $sent > 0.00001) {
                    throw ValidationException::withMessages(["items.{$index}.quantity_received" => "Enter between 0 and {$this->qty($sent)} for {$item->name}."]);
                }
                $short = round($sent - $got, 4);

                $rows = $line->consumptions()->withoutGlobalScopes()->orderBy('id')->get();
                [$arrived, $missing] = $this->splitPieces($rows, $got);

                $receivedCost = 0.0;
                if ($got > 0) {
                    $receivedCost = $this->putInDestination($transfer, $item, $arrived, $got, $date);
                    $this->addOnHand($transfer->tenant_id, $item->id, $to->id, $got, $receivedCost);
                    $this->history($transfer, $item->id, $to->id, $got, "Transfer {$transfer->transfer_number} from {$from->name}");
                }

                $returned = 0.0;
                $lost = 0.0;
                $lostCost = 0.0;
                if ($short > 0) {
                    if ($shortfall === self::RETURN) {
                        $this->returnPieces($transfer, $item, $missing,
                            "Transfer {$transfer->transfer_number}: {$this->qty($short)} not received, back in {$from->name}");
                        $returned = $short;
                    } else {
                        $lost = $short;
                        $lostCost = $this->piecesCost($missing);
                        $lostLines[] = [$item, $lost, $lostCost];
                    }
                }

                $line->forceFill([
                    'quantity_received' => $got,
                    'quantity_returned' => $returned,
                    'quantity_lost' => $lost,
                    'received_cost' => $receivedCost,
                    'lost_cost' => $lostCost,
                ])->save();
            }

            $transfer->status = StockTransferStatus::Received->value;
            $transfer->received_at = now();
            $transfer->received_date = Carbon::parse($date);
            $transfer->save();

            $this->postLosses($transfer, $lostLines, $date);

            return $transfer->fresh(['items']);
        });
    }

    /**
     * Cost layers in the destination for the goods that arrived, at the
     * cost they left at. Returns their cost.
     *
     * @param  array<int, array{0: InventoryLayerConsumption, 1: float}>  $pieces
     */
    private function putInDestination(StockTransfer $transfer, Item $item, array $pieces, float $quantity, string $date): float
    {
        $cost = $this->piecesCost($pieces);

        if (($item->valuation_method ?? 'weighted_average') !== 'fifo') {
            $this->valuation()->addLayer($transfer->tenant_id, $item->id, $quantity, round($cost / $quantity, 4),
                $transfer->to_warehouse_id, 'stock_transfer', $transfer->id)
                ->forceFill(['received_date' => $date])->save();

            return $cost;
        }

        foreach ($pieces as [$row, $qty]) {
            $source = $row->layer()->withoutGlobalScopes()->first();
            $layer = $this->valuation()->addLayer($transfer->tenant_id, $item->id, $qty, (float) $row->unit_cost,
                $transfer->to_warehouse_id, 'stock_transfer', $transfer->id, $source?->batch_number);
            // Keep the purchase date, so FIFO still sells the oldest goods first.
            $layer->forceFill(['received_date' => $source->received_date ?? $date])->save();
        }

        return $cost;
    }

    /**
     * Dr Stock losses, Cr Inventory for goods lost on the way.
     *
     * @param  array<int, array{0: Item, 1: float, 2: float}>  $lostLines
     */
    private function postLosses(StockTransfer $transfer, array $lostLines, string $date): void
    {
        $total = round(array_sum(array_column($lostLines, 2)), 2);
        if ($total <= 0) {
            return;
        }

        $t = (int) $transfer->tenant_id;
        $what = implode(', ', array_map(fn ($l) => $this->qty($l[1]).' '.$l[0]->name, $lostLines));
        $this->journals->postLines($transfer, $transfer->transfer_number, $date,
            "Stock lost in transfer {$transfer->transfer_number}: {$what}", [
                [AccountCodeService::resolve($t, 'stock_losses'), $total, 0.0, "Lost on the way: {$what}"],
                [AccountCodeService::resolve($t, 'inventory'), 0.0, $total, "Stock lost in transfer {$transfer->transfer_number}"],
            ]);
    }
}
