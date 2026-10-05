<?php

namespace App\Actions\StockTransfers;

use App\Enums\StockTransferStatus;
use App\Models\Item;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelling a shipped transfer (session 13): the goods go back to the
 * source warehouse, each piece into the cost layer it left, so the source
 * gets back exactly the cost it lost. Checked against the shipping date,
 * since that is the movement being undone. A received transfer can't be
 * cancelled: make a transfer the other way instead.
 */
class CancelStockTransfer
{
    use MovesTransferStock;

    public function handle(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer = StockTransfer::withoutGlobalScopes()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($reason = $this->blockedBecause($transfer)) {
                throw ValidationException::withMessages(['transfer' => $reason]);
            }
            $this->assertDateOpen($transfer, $transfer->transfer_date, 'transfer_date');

            $from = $transfer->fromWarehouse()->withoutGlobalScopes()->first();
            foreach ($transfer->items()->get() as $line) {
                $item = Item::withoutGlobalScopes()->findOrFail($line->item_id);
                $rows = $line->consumptions()->withoutGlobalScopes()->orderBy('id')->get();
                [, $all] = $this->splitPieces($rows, 0);
                $this->returnPieces($transfer, $item, $all, "Transfer {$transfer->transfer_number} cancelled, back in {$from->name}");
                $line->forceFill(['quantity_returned' => $line->quantity])->save();
            }

            $transfer->status = StockTransferStatus::Cancelled->value;
            $transfer->cancelled_at = now();
            $transfer->save();

            return $transfer->fresh(['items']);
        });
    }

    public function blockedBecause(StockTransfer $transfer): ?string
    {
        return match ($transfer->status) {
            StockTransfer::STATUS_IN_TRANSIT => null,
            StockTransfer::STATUS_DRAFT => "Transfer {$transfer->transfer_number} hasn't been shipped. Delete it instead.",
            StockTransfer::STATUS_RECEIVED => "Transfer {$transfer->transfer_number} has been received, so it can't be cancelled. To undo it, transfer the goods back.",
            default => "Transfer {$transfer->transfer_number} is already cancelled.",
        };
    }
}
