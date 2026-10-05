<?php

namespace App\Actions\StockTransfers;

use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a stock transfer (session 13). Only a draft, which has moved
 * nothing; a shipped transfer is cancelled instead.
 */
class DeleteStockTransfer
{
    use MovesTransferStock;

    public function handle(StockTransfer $transfer): void
    {
        if ($reason = $this->blockedBecause($transfer)) {
            throw ValidationException::withMessages(['transfer' => $reason]);
        }
        $this->assertDateOpen($transfer, $transfer->transfer_date, 'transfer_date');

        DB::transaction(function () use ($transfer) {
            $transfer->items()->delete();
            $transfer->delete();
        });
    }

    public function blockedBecause(StockTransfer $transfer): ?string
    {
        return $transfer->isDraft() ? null
            : "Transfer {$transfer->transfer_number} has been shipped, so it can't be deleted.".($transfer->isInTransit() ? ' Cancel it instead.' : '');
    }
}
